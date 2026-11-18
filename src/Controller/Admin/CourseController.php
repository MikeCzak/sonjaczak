<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Course\Grid\CourseGrid;
use App\Course\HolidayImporter;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Lesson;
use App\Entity\User;
use App\Form\Admin\CourseBreakType;
use App\Form\Admin\CourseType;
use App\Lesson\BreakResult;
use App\Lesson\LessonDescriber;
use App\Lesson\LessonPlanner;
use App\Lesson\MakeUpRule;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\SettingsRepository;
use App\Repository\TrialLessonRepository;
use App\Security\Voter\CourseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Courses and their breaks (step C2). New courses get holiday suggestions from the OpenHolidays API.
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/kurse')]
final class CourseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LessonPlanner $planner,
        private readonly SettingsRepository $settings,
        private readonly ClockInterface $clock,
        private readonly LessonDescriber $describer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_course_index', methods: ['GET'])]
    public function index(CourseRepository $courses): Response
    {
        return $this->render('admin/course/index.html.twig', [
            'courses' => $courses->findBy([], ['startsOn' => 'DESC']),
        ]);
    }

    #[Route('/neu', name: 'admin_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request, HolidayImporter $holidays): Response
    {
        $settings = $this->settings->current();
        $course = new Course()
            ->setLessonMinutes($settings->getLessonMinutes())
            ->setPlannedLessons($settings->getPlannedLessons());
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($course);
            $this->flashImportResult($holidays->importInto($course));
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
        }

        return $this->render('admin/course/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_course_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, MakeUpRule $makeUpRule): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'course.flash.saved');

            return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
        }

        return $this->renderEdit($course, $form, $this->createBreakForm($course, $this->prefilledBreak($request), $this->fromGrid($request)), $makeUpRule);
    }

    /**
     * The course grid (step C4b; a time axis per teaching day since C4g), with the trial lessons in the course's time
     * span. Every lesson links to its edit page, so it also works without JavaScript.
     */
    #[Route('/{id}/raster', name: 'admin_course_grid', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function grid(Course $course, EnrollmentRepository $enrollments, TrialLessonRepository $trials, CourseGrid $courseGrid): Response
    {
        $from = $course->getStartsOn();
        $until = $course->getEndsOn()?->modify('+1 day');
        $trialList = null !== $from && null !== $until ? $trials->findBetween($from, $until) : [];

        return $this->render('admin/course/grid.html.twig', [
            'course' => $course,
            'grid' => $courseGrid->build($course, $enrollments->findForGrid($course), $trialList, $this->clock->now()),
        ]);
    }

    /**
     * A break entered by hand. Lessons in it are deleted (question 6); the browser asked before (break-confirm
     * controller) and sends confirmed=1. Without that, e.g. without JavaScript, nothing is saved and the flash lists
     * the lessons, so nothing is deleted unseen.
     */
    #[Route('/{id}/ferien', name: 'admin_course_break_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function newBreak(Request $request, Course $course, MakeUpRule $makeUpRule): Response
    {
        $fromGrid = $this->fromGrid($request);
        $break = new CourseBreak();
        $breakForm = $this->createBreakForm($course, $break, $fromGrid);
        $breakForm->handleRequest($request);

        if ($breakForm->isSubmitted() && $breakForm->isValid() && null !== $break->getFirstDay() && null !== $break->getLastDay()) {
            $affected = $this->planner->lessonsBetween($course, $break->getFirstDay(), $break->getLastDay());
            if ([] !== $affected && !$this->isConfirmed($request)) {
                $this->flashNotConfirmed($affected);

                return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
            }
            $course->addBreak($break);
            $this->flashBreakResult('course_break.flash.saved', $this->planner->applyBreaks($course));
            $this->entityManager->flush();

            return $fromGrid
                ? $this->redirectToRoute('admin_course_grid', ['id' => $course->getId()])
                : $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
        }

        // Show the errors on the course page, with the course form unchanged
        return $this->renderEdit($course, $this->createForm(CourseType::class, $course), $breakForm, $makeUpRule);
    }

    /**
     * For the popup before a break is saved: which lessons lie between von and bis (both "Y-m-d", bis optional).
     * Answers {"confirm": "…"} or {"confirm": null} if none.
     */
    #[Route('/{id}/ferien/pruefen', name: 'admin_course_break_check', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function checkBreak(Request $request, Course $course): JsonResponse
    {
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('von'));
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('bis')) ?: $first;
        if (false === $first || false === $last) {
            // Invalid dates: the form's validation will say so, nothing to confirm
            return $this->json(['confirm' => null]);
        }

        return $this->confirmAnswer($this->planner->lessonsBetween($course, $first, $last));
    }

    /**
     * For the popup before "Ferien vorschlagen lassen": asks the API and looks which lessons the new breaks would
     * hit. Nothing is saved (no flush); the request that really imports asks the API again.
     */
    #[Route('/{id}/ferien/vorschlag-pruefen', name: 'admin_course_break_import_check', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function checkImport(Course $course, HolidayImporter $holidays): JsonResponse
    {
        $holidays->importInto($course);

        return $this->confirmAnswer($this->planner->lessonsOnBreakDays($course));
    }

    /**
     * Deleting a break gives the lessons on its days back; the last ones at the end go (question 7).
     */
    #[Route('/{id}/ferien/{breakId}/loeschen', name: 'admin_course_break_delete', requirements: ['id' => '\d+', 'breakId' => '\d+'], methods: ['POST'])]
    public function deleteBreak(Request $request, Course $course, #[MapEntity(id: 'breakId')] CourseBreak $break): Response
    {
        // The break must belong to this course, not to any course with a guessed id
        if ($break->getCourse() !== $course) {
            throw new NotFoundHttpException();
        }

        if ($this->isCsrfTokenValid('delete-break-'.$break->getId(), $request->getPayload()->getString('_token'))) {
            // orphanRemoval: removing it from the collection deletes the row
            $course->removeBreak($break);
            $this->flashBreakResult('course_break.flash.deleted', $this->planner->restoreBreakDays($course, $break, $this->clock->now()));
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
    }

    /**
     * Ask the API again, e.g. if it was not reachable when the course was created. Adds only missing breaks.
     */
    #[Route('/{id}/ferien/vorschlagen', name: 'admin_course_break_import', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function importBreaks(Request $request, Course $course, HolidayImporter $holidays): Response
    {
        if ($this->isCsrfTokenValid('import-breaks-'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $added = $holidays->importInto($course);
            $affected = $this->planner->lessonsOnBreakDays($course);
            if ([] !== $affected && !$this->isConfirmed($request)) {
                // Not flushed: the suggested breaks are not saved
                $this->flashNotConfirmed($affected);

                return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
            }
            $this->flashImportResult($added);
            $this->flashBreakResult(null, $this->planner->applyBreaks($course));
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
    }

    #[Route('/{id}/loeschen', name: 'admin_course_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CourseVoter::DELETE, 'course')]
    public function delete(Request $request, Course $course): Response
    {
        if ($this->isCsrfTokenValid('delete-course-'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($course);
            $this->entityManager->flush();
            $this->addFlash('success', 'course.flash.deleted');
        }

        return $this->redirectToRoute('admin_course_index');
    }

    /**
     * @return FormInterface<CourseBreak>
     */
    private function createBreakForm(Course $course, ?CourseBreak $break = null, bool $fromGrid = false): FormInterface
    {
        return $this->createForm(CourseBreakType::class, $break ?? new CourseBreak(), [
            // Coming from the grid (C4e): back there after saving
            'action' => $this->generateUrl('admin_course_break_new', ['id' => $course->getId()] + ($fromGrid ? ['zurueck' => 'raster'] : [])),
        ]);
    }

    /**
     * The week button of the grid links here with ferien_von and ferien_bis (C4e): the form comes prefilled.
     */
    private function prefilledBreak(Request $request): ?CourseBreak
    {
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('ferien_von'));
        if (false === $first) {
            return null;
        }
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('ferien_bis'));

        return new CourseBreak('', $first, false === $last ? null : $last);
    }

    private function fromGrid(Request $request): bool
    {
        return 'raster' === $request->query->getString('zurueck');
    }

    private function isConfirmed(Request $request): bool
    {
        return '1' === $request->getPayload()->getString('confirmed');
    }

    /**
     * @param list<Lesson> $affected
     */
    private function confirmAnswer(array $affected): JsonResponse
    {
        return $this->json(['confirm' => [] === $affected ? null : $this->translator->trans('course_break.confirm', [
            '%lessons%' => $this->describer->describe($affected),
        ], 'admin')]);
    }

    /**
     * @param list<Lesson> $affected
     */
    private function flashNotConfirmed(array $affected): void
    {
        $this->addFlash('warning', $this->translator->trans('course_break.flash.not_confirmed', [
            '%lessons%' => str_replace("\n", '; ', $this->describer->describe($affected)),
        ], 'admin'));
    }

    private function flashBreakResult(?string $message, BreakResult $result): void
    {
        if (null !== $message) {
            $this->addFlash('success', $message);
        }
        if ($result->removed > 0 || $result->added > 0) {
            $this->addFlash('info', $this->translator->trans('course_break.flash.lessons', [
                '%removed%' => $result->removed,
                '%added%' => $result->added,
            ], 'admin'));
        }
    }

    /**
     * @param FormInterface<Course>      $form
     * @param FormInterface<CourseBreak> $breakForm
     */
    private function renderEdit(Course $course, FormInterface $form, FormInterface $breakForm, MakeUpRule $makeUpRule): Response
    {
        $makeUpsDue = [];
        foreach ($course->getEnrollments() as $enrollment) {
            $makeUpsDue[(int) $enrollment->getId()] = $makeUpRule->due($enrollment);
        }

        return $this->render('admin/course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
            'break_form' => $breakForm,
            'make_ups_due' => $makeUpsDue,
        ]);
    }

    private function flashImportResult(?int $added): void
    {
        if (null === $added) {
            $this->addFlash('warning', 'course_break.flash.import_failed');
        } else {
            $this->addFlash('success', 0 === $added ? 'course_break.flash.import_none' : 'course_break.flash.imported');
        }
    }
}
