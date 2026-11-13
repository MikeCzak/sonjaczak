<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mein Konto": the own account. Passkeys follow in step B5.
 */
#[IsGranted('ROLE_ADMIN')]
final class AccountController extends AbstractController
{
    #[Route('/admin/konto', name: 'admin_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('newPassword')->getData();
            \assert(\is_string($password));
            // A new hash also logs out other sessions of this account: their stored checksum no longer matches
            // (User::__serialize()); this session is updated by Symfony and stays logged in
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $entityManager->flush();

            $this->addFlash('success', 'password.flash.changed');

            return $this->redirectToRoute('admin_account');
        }

        return $this->render('admin/account/index.html.twig', ['form' => $form]);
    }
}
