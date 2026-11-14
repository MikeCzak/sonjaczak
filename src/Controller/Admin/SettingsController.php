<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\SettingsType;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Settings (step C6): theme of the public site, defaults for lessons, sender data for invoices.
 */
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/admin/einstellungen')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request, SettingsRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $settings = $repository->current();
        $form = $this->createForm(SettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // First save creates the one row
            $entityManager->persist($settings);
            $entityManager->flush();
            $this->addFlash('success', 'settings.flash.saved');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/edit.html.twig', ['form' => $form, 'settings' => $settings]);
    }
}
