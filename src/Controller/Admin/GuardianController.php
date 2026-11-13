<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Guardian;
use App\Entity\User;
use App\Form\Admin\GuardianType;
use App\Repository\GuardianRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Parents and guardians (step C1), the invoice recipients for minors.
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/elternteile')]
final class GuardianController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'admin_guardian_index', methods: ['GET'])]
    public function index(GuardianRepository $guardians): Response
    {
        return $this->render('admin/guardian/index.html.twig', [
            'guardians' => $guardians->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/neu', name: 'admin_guardian_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $guardian = new Guardian();
        $form = $this->createForm(GuardianType::class, $guardian);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($guardian);
            $this->entityManager->flush();
            $this->addFlash('success', 'guardian.flash.saved');

            return $this->redirectToRoute('admin_guardian_index');
        }

        return $this->render('admin/guardian/form.html.twig', ['form' => $form, 'guardian' => null]);
    }

    #[Route('/{id}', name: 'admin_guardian_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Guardian $guardian): Response
    {
        $form = $this->createForm(GuardianType::class, $guardian);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'guardian.flash.saved');

            return $this->redirectToRoute('admin_guardian_index');
        }

        return $this->render('admin/guardian/form.html.twig', ['form' => $form, 'guardian' => $guardian]);
    }

    /**
     * Only without students: the foreign key student.guardian_id would refuse it anyway, this gives a readable
     * message instead of a database error.
     */
    #[Route('/{id}/loeschen', name: 'admin_guardian_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Guardian $guardian): Response
    {
        if (!$this->isCsrfTokenValid('delete-guardian-'.$guardian->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_guardian_index');
        }

        if (!$guardian->getStudents()->isEmpty()) {
            $this->addFlash('danger', 'guardian.flash.has_students');

            return $this->redirectToRoute('admin_guardian_edit', ['id' => $guardian->getId()]);
        }

        $this->entityManager->remove($guardian);
        $this->entityManager->flush();
        $this->addFlash('success', 'guardian.flash.deleted');

        return $this->redirectToRoute('admin_guardian_index');
    }
}
