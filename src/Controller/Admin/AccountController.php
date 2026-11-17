<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Passkey;
use App\Entity\User;
use App\Form\Admin\ChangePasswordType;
use App\Repository\PasskeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mein Konto": the own account, password and passkeys (umsetzung-admin.md B5b). Adding a passkey runs through the
 * WebAuthn bundle's endpoints (config/packages/webauthn.yaml), listing and deleting here.
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
        PasskeyRepository $passkeys,
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

        return $this->render('admin/account/index.html.twig', ['form' => $form, 'passkeys' => $passkeys->findForUser($user)]);
    }

    /**
     * Only the own passkeys: someone else's is "not found", like one that does not exist.
     */
    #[Route('/admin/konto/passkeys/{id}/loeschen', name: 'admin_passkey_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePasskey(Request $request, Passkey $passkey, #[CurrentUser] User $user, EntityManagerInterface $entityManager): Response
    {
        if ($passkey->userHandle !== $user->getWebauthnHandle()) {
            throw $this->createNotFoundException('Not a passkey of this account.');
        }

        if ($this->isCsrfTokenValid('delete-passkey-'.$passkey->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($passkey);
            $entityManager->flush();
            $this->addFlash('success', 'account.passkey.flash.deleted');
        }

        return $this->redirectToRoute('admin_account');
    }
}
