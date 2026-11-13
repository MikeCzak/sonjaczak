<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Lesson;
use App\Entity\User;
use App\Form\Admin\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mark or move a single lesson. Replaced for daily use by the course grid (step C4).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class LessonController extends AbstractController
{
    #[Route('/admin/einheiten/{id}', name: 'admin_lesson_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'lesson.flash.saved');

            return $this->redirectToRoute('admin_enrollment_show', ['id' => $lesson->getEnrollment()->getId()]);
        }

        return $this->render('admin/lesson/edit.html.twig', ['lesson' => $lesson, 'form' => $form]);
    }
}
