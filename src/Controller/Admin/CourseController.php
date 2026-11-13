<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Course\HolidayImporter;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\User;
use App\Form\Admin\CourseBreakType;
use App\Form\Admin\CourseType;
use App\Lesson\LessonPlanner;
use App\Lesson\MakeUpRule;
use App\Repository\CourseRepository;
use App\Repository\SettingsRepository;
use App\Security\Voter\CourseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        $course = new Course()->setLessonMinutes($this->settings->current()->getLessonMinutes());
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

        return $this->renderEdit($course, $form, $this->createBreakForm($course), $makeUpRule);
    }

    #[Route('/{id}/ferien', name: 'admin_course_break_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function newBreak(Request $request, Course $course, MakeUpRule $makeUpRule): Response
    {
        $break = new CourseBreak();
        $breakForm = $this->createBreakForm($course, $break);
        $breakForm->handleRequest($request);

        if ($breakForm->isSubmitted() && $breakForm->isValid()) {
            $course->addBreak($break);
            $this->applyBreaks($course);
            $this->entityManager->flush();
            $this->addFlash('success', 'course_break.flash.saved');

            return $this->redirectToRoute('admin_course_edit', ['id' => $course->getId()]);
        }

        // Show the errors on the course page, with the course form unchanged
        return $this->renderEdit($course, $this->createForm(CourseType::class, $course), $breakForm, $makeUpRule);
    }

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
            $this->entityManager->flush();
            $this->addFlash('success', 'course_break.flash.deleted');
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
            $this->flashImportResult($holidays->importInto($course));
            $this->applyBreaks($course);
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
    private function createBreakForm(Course $course, ?CourseBreak $break = null): FormInterface
    {
        return $this->createForm(CourseBreakType::class, $break ?? new CourseBreak(), [
            'action' => $this->generateUrl('admin_course_break_new', ['id' => $course->getId()]),
        ]);
    }

    /**
     * @param FormInterface<Course>      $form
     * @param FormInterface<CourseBreak> $breakForm
     */
    private function renderEdit(Course $course, FormInterface $form, FormInterface $breakForm, MakeUpRule $makeUpRule): Response
    {
        $threshold = $this->settings->current()->getMakeUpThreshold();
        $makeUpsDue = [];
        foreach ($course->getEnrollments() as $enrollment) {
            $makeUpsDue[(int) $enrollment->getId()] = $makeUpRule->due($enrollment, $threshold);
        }

        return $this->render('admin/course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
            'break_form' => $breakForm,
            'make_ups_due' => $makeUpsDue,
        ]);
    }

    /**
     * New breaks: planned lessons in them go, the enrollments are filled up at the end (LessonPlanner).
     * Held or cancelled lessons in a break stay; Sonja gets a warning to look at them.
     */
    private function applyBreaks(Course $course): void
    {
        if ([] !== $this->planner->applyBreaks($course)->conflicts) {
            $this->addFlash('warning', 'course_break.flash.conflicts');
        }
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
