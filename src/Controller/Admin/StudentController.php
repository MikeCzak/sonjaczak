<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Student;
use App\Entity\User;
use App\Form\Admin\StudentType;
use App\Repository\StudentRepository;
use App\Security\Voter\StudentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Students (step C1). Personal data: only for ROLE_ADMIN, never logged.
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/schueler')]
final class StudentController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'admin_student_index', methods: ['GET'])]
    public function index(StudentRepository $students, #[MapQueryParameter] ?string $q = null): Response
    {
        return $this->render('admin/student/index.html.twig', [
            'students' => $students->search($q),
            'q' => $q,
        ]);
    }

    #[Route('/neu', name: 'admin_student_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $student = new Student();
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($student);
            $this->entityManager->flush();
            $this->addFlash('success', 'student.flash.saved');

            return $this->redirectToRoute('admin_student_index');
        }

        return $this->render('admin/student/form.html.twig', ['form' => $form, 'student' => null]);
    }

    #[Route('/{id}', name: 'admin_student_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Student $student): Response
    {
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'student.flash.saved');

            return $this->redirectToRoute('admin_student_index');
        }

        return $this->render('admin/student/form.html.twig', ['form' => $form, 'student' => $student]);
    }

    #[Route('/{id}/loeschen', name: 'admin_student_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(StudentVoter::DELETE, 'student')]
    public function delete(Request $request, Student $student): Response
    {
        if ($this->isCsrfTokenValid('delete-student-'.$student->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($student);
            $this->entityManager->flush();
            $this->addFlash('success', 'student.flash.deleted');
        }

        return $this->redirectToRoute('admin_student_index');
    }
}
