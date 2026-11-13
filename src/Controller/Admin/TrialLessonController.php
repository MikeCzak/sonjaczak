<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TrialLesson;
use App\Entity\User;
use App\Form\Admin\TrialLessonType;
use App\Repository\SettingsRepository;
use App\Repository\StudentRepository;
use App\Repository\TrialLessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Trial lessons (step C5). Taken over into a course when the enrollment is created (EnrollmentController::new()).
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/schnupperstunden')]
final class TrialLessonController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'admin_trial_index', methods: ['GET'])]
    public function index(TrialLessonRepository $trials): Response
    {
        return $this->render('admin/trial/index.html.twig', ['trials' => $trials->findAllWithStudent()]);
    }

    /**
     * "?student=…" preselects the student, e.g. coming from the page of a newly created student.
     */
    #[Route('/neu', name: 'admin_trial_new', methods: ['GET', 'POST'])]
    public function new(Request $request, StudentRepository $students, SettingsRepository $settings, #[MapQueryParameter] ?int $student = null): Response
    {
        $trial = new TrialLesson()
            ->setStudent(null === $student ? null : $students->find($student))
            ->setDurationMinutes($settings->current()->getLessonMinutes());
        $form = $this->createForm(TrialLessonType::class, $trial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($trial);
            $this->entityManager->flush();
            $this->addFlash('success', 'trial.flash.saved');

            return $this->redirectToRoute('admin_trial_index');
        }

        return $this->render('admin/trial/form.html.twig', ['form' => $form, 'trial' => null]);
    }

    #[Route('/{id}', name: 'admin_trial_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, TrialLesson $trial): Response
    {
        $form = $this->createForm(TrialLessonType::class, $trial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'trial.flash.saved');

            return $this->redirectToRoute('admin_trial_index');
        }

        return $this->render('admin/trial/form.html.twig', ['form' => $form, 'trial' => $trial]);
    }

    #[Route('/{id}/loeschen', name: 'admin_trial_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, TrialLesson $trial): Response
    {
        if ($this->isCsrfTokenValid('delete-trial-'.$trial->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($trial);
            $this->entityManager->flush();
            $this->addFlash('success', 'trial.flash.deleted');
        }

        return $this->redirectToRoute('admin_trial_index');
    }
}
