<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\Student;
use App\Entity\TrialLesson;
use App\Entity\User;
use App\Form\Admin\EnrollmentType;
use App\Form\Admin\MakeUpLessonType;
use App\Form\Admin\PlannedLessonsType;
use App\Lesson\LessonPlanner;
use App\Lesson\MakeUpRule;
use App\Lesson\TrialTakeover;
use App\Privacy\RetentionPolicy;
use App\Repository\StudentRepository;
use App\Security\Voter\EnrollmentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Enrollments and their lessons (step C3). Creating an enrollment generates its lessons (LessonPlanner).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class EnrollmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LessonPlanner $planner,
        private readonly MakeUpRule $makeUpRule,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Two steps without JavaScript: first choose the student (GET), then the form with defaults that depend on the
     * student (bill to guardian if a minor at the start of the course).
     */
    #[Route('/admin/kurse/{id}/teilnahmen/neu', name: 'admin_enrollment_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        Course $course,
        StudentRepository $students,
        TrialTakeover $takeover,
        #[MapQueryParameter] ?int $student = null,
    ): Response {
        $chosen = null === $student ? null : $students->find($student);
        if (!$chosen instanceof Student) {
            return $this->render('admin/enrollment/choose_student.html.twig', [
                'course' => $course,
                'students' => $students->notEnrolledIn($course),
            ]);
        }

        $enrollment = new Enrollment($course, $chosen);
        $form = $this->createForm(EnrollmentType::class, $enrollment, [
            'with_slot' => true,
            'trial_lessons' => $chosen->getTrialLessons()->getValues(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var iterable<TrialLesson> $trials */
            $trials = $form->has('trials') ? $form->get('trials')->getData() : [];
            $takeover->enroll($enrollment, $trials);
            $this->addFlash('success', 'enrollment.flash.created');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        return $this->render('admin/enrollment/new.html.twig', ['course' => $course, 'enrollment' => $enrollment, 'form' => $form]);
    }

    #[Route('/admin/teilnahmen/{id}', name: 'admin_enrollment_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, Enrollment $enrollment, RetentionPolicy $retention): Response
    {
        // Personal data deleted, only the invoices are kept (F3): nothing left to edit
        if ($enrollment->isPersonDeleted()) {
            return $this->render('admin/enrollment/show_person_deleted.html.twig', [
                'enrollment' => $enrollment,
                'invoices_until' => $retention->enrollmentInvoicesUntil($enrollment),
            ]);
        }

        $form = $this->createForm(EnrollmentType::class, $enrollment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'enrollment.flash.saved');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        return $this->renderShow($enrollment, $form);
    }

    /**
     * Changes the planned number of lessons (umsetzung-admin.md C7): lower when lessons are dropped without
     * replacement, the minimum ("end") when the enrollment ends early, higher to add lessons at the end. Lessons that
     * would go are listed first and only deleted after "confirm". Money is never changed here: with an invoice, the
     * page points to the credit note (Sonja decides about goodwill).
     */
    #[Route('/admin/teilnahmen/{id}/anzahl', name: 'admin_enrollment_planned', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function plannedLessons(Request $request, Enrollment $enrollment): Response
    {
        if ($enrollment->isPersonDeleted()) {
            throw $this->createNotFoundException('The personal data of this enrollment was deleted.');
        }
        $now = $this->clock->now();
        $minimum = $this->planner->minimumPlannedLessons($enrollment, $now);
        $plannedForm = $this->plannedForm($enrollment, $minimum);
        $plannedForm->handleRequest($request);

        if (!$plannedForm->isSubmitted() || !$plannedForm->isValid()) {
            return $this->renderShow($enrollment, plannedForm: $plannedForm, status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var array{plannedLessons: int|null, reason: string|null} $data */
        $data = $plannedForm->getData();
        $count = $this->isClicked($plannedForm->get('end')) ? $minimum : (int) $data['plannedLessons'];
        $reason = trim((string) $data['reason']);
        $surplus = $this->planner->surplusLessons($enrollment, $count, $now);

        if ([] !== $surplus && !$this->isClicked($plannedForm->get('confirm'))) {
            // Confirmation step: the same form with the number resolved ("end" → minimum) and the confirm button
            return $this->renderShow($enrollment, plannedForm: $this->plannedForm($enrollment, $minimum, [
                'plannedLessons' => $count,
                'reason' => $reason,
            ]), surplus: $surplus);
        }

        $before = $enrollment->getPlannedLessons();
        $result = $this->planner->changePlannedLessons($enrollment, $count, $now);
        $enrollment->setPlannedLessonsReason($reason);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('enrollment.planned.flash.saved', [
            '%count%' => $count,
            '%removed%' => $result->removed,
            '%added%' => $result->added,
        ], 'admin'));
        $regular = $enrollment->getLessons()->filter(static fn (Lesson $lesson): bool => null === $lesson->getMakeUpFor())->count();
        if ($regular < $count) {
            $this->addFlash('warning', $this->translator->trans('enrollment.planned.flash.course_end', ['%count%' => $regular], 'admin'));
        }
        if ($count < $before && !$enrollment->getInvoices()->isEmpty()) {
            $this->addFlash('info', 'enrollment.planned.flash.invoice_hint');
        }

        return $this->redirect($this->generateUrl('admin_enrollment_show', ['id' => $enrollment->getId()]).'#anzahl');
    }

    #[Route('/admin/teilnahmen/{id}/nachholen', name: 'admin_enrollment_make_up', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function makeUp(Request $request, Enrollment $enrollment): Response
    {
        $cancelled = $this->cancelledWithoutMakeUp($enrollment);
        if ([] === $cancelled) {
            // Reached from a "Nachholstunde fällig" badge although no cancellation is left to make up: say why
            if ($this->makeUpRule->due($enrollment) > 0) {
                $this->addFlash('info', 'lesson.flash.make_up_nothing_cancelled');
            }

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        // Proposed time: same slot one week after the last lesson
        $last = $enrollment->getLessons()->last();
        $lesson = new Lesson($enrollment, ($last ? $last->getStartsAt() : null)?->modify('+1 week') ?? new \DateTimeImmutable('tomorrow 15:00'));
        $form = $this->createForm(MakeUpLessonType::class, $lesson, ['cancelled_lessons' => $cancelled]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'lesson.flash.make_up_created');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        // Not saved: the lesson was added to the collection in its constructor, take it out again
        $enrollment->removeLesson($lesson);

        return $this->render('admin/enrollment/make_up.html.twig', ['enrollment' => $enrollment, 'form' => $form]);
    }

    #[Route('/admin/teilnahmen/{id}/loeschen', name: 'admin_enrollment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(EnrollmentVoter::DELETE, 'enrollment')]
    public function delete(Request $request, Enrollment $enrollment): Response
    {
        $course = $enrollment->getCourse();
        if ($this->isCsrfTokenValid('delete-enrollment-'.$enrollment->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($enrollment);
            $this->entityManager->flush();
            $this->addFlash('success', 'enrollment.flash.deleted');
        }

        return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
    }

    /**
     * @param FormInterface<Enrollment>|null                                               $form
     * @param FormInterface<array{plannedLessons: int|null, reason: string|null}>|null $plannedForm
     * @param list<Lesson>                                                             $surplus     lessons to confirm (C7)
     */
    private function renderShow(
        Enrollment $enrollment,
        ?FormInterface $form = null,
        ?FormInterface $plannedForm = null,
        array $surplus = [],
        int $status = Response::HTTP_OK,
    ): Response {
        $minimum = $this->planner->minimumPlannedLessons($enrollment, $this->clock->now());

        return $this->render('admin/enrollment/show.html.twig', [
            'enrollment' => $enrollment,
            'form' => $form ?? $this->createForm(EnrollmentType::class, $enrollment),
            'planned_form' => $plannedForm ?? $this->plannedForm($enrollment, $minimum),
            'planned_minimum' => $minimum,
            'surplus' => $surplus,
            'make_up_due' => $this->makeUpRule->due($enrollment),
            'can_make_up' => [] !== $this->cancelledWithoutMakeUp($enrollment),
        ], new Response(status: $status));
    }

    /**
     * @param array{plannedLessons: int|null, reason: string|null}|null $data
     *
     * @return FormInterface<array{plannedLessons: int|null, reason: string|null}>
     */
    private function plannedForm(Enrollment $enrollment, int $minimum, ?array $data = null): FormInterface
    {
        return $this->createForm(PlannedLessonsType::class, $data ?? [
            'plannedLessons' => $enrollment->getPlannedLessons(),
            'reason' => $enrollment->getPlannedLessonsReason(),
        ], [
            'minimum' => $minimum,
            'action' => $this->generateUrl('admin_enrollment_planned', ['id' => $enrollment->getId()]),
        ]);
    }

    /**
     * @param FormInterface<mixed> $button
     */
    private function isClicked(FormInterface $button): bool
    {
        return $button instanceof ClickableInterface && $button->isClicked();
    }

    /**
     * Lessons Sonja cancelled that no make-up lesson points to yet.
     *
     * @return list<Lesson>
     */
    private function cancelledWithoutMakeUp(Enrollment $enrollment): array
    {
        $madeUp = [];
        foreach ($enrollment->getLessons() as $lesson) {
            if (null !== $lesson->getMakeUpFor()) {
                $madeUp[] = $lesson->getMakeUpFor();
            }
        }

        return array_values(array_filter(
            $enrollment->getLessons()->toArray(),
            static fn (Lesson $lesson): bool => CancelledBy::Teacher === $lesson->getCancelledBy() && !\in_array($lesson, $madeUp, true),
        ));
    }
}
