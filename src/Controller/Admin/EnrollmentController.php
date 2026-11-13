<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Entity\Student;
use App\Entity\User;
use App\Form\Admin\EnrollmentType;
use App\Form\Admin\MakeUpLessonType;
use App\Lesson\LessonPlanner;
use App\Lesson\MakeUpRule;
use App\Repository\StudentRepository;
use App\Security\Voter\EnrollmentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Enrollments and their lessons (step C3). Creating an enrollment generates its lessons (LessonPlanner).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class EnrollmentController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
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
        LessonPlanner $planner,
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
        $form = $this->createForm(EnrollmentType::class, $enrollment, ['with_slot' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($enrollment);
            $planner->fillUp($enrollment);
            $this->entityManager->flush();
            $this->addFlash('success', 'enrollment.flash.created');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        return $this->render('admin/enrollment/new.html.twig', ['course' => $course, 'enrollment' => $enrollment, 'form' => $form]);
    }

    #[Route('/admin/teilnahmen/{id}', name: 'admin_enrollment_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, Enrollment $enrollment, MakeUpRule $makeUpRule): Response
    {
        $form = $this->createForm(EnrollmentType::class, $enrollment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'enrollment.flash.saved');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $enrollment->getId()]);
        }

        return $this->render('admin/enrollment/show.html.twig', [
            'enrollment' => $enrollment,
            'form' => $form,
            'make_up_due' => $makeUpRule->due($enrollment),
            'can_make_up' => [] !== $this->cancelledWithoutMakeUp($enrollment),
        ]);
    }

    #[Route('/admin/teilnahmen/{id}/nachholen', name: 'admin_enrollment_make_up', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function makeUp(Request $request, Enrollment $enrollment): Response
    {
        $cancelled = $this->cancelledWithoutMakeUp($enrollment);
        if ([] === $cancelled) {
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
            static fn (Lesson $lesson): bool => LessonStatus::CancelledByTeacher === $lesson->getStatus() && !\in_array($lesson, $madeUp, true),
        ));
    }
}
