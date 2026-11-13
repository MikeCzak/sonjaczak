<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\NewPasswordType;
use App\Form\Admin\PasswordRequestType;
use App\Repository\UserRepository;
use App\Security\PasswordLinkMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * "Passwort vergessen" and setting a password from a mailed link (also used by invitations).
 * Public pages of the admin firewall (access_control in config/packages/security.yaml). Based on the code of
 * make:reset-password (symfonycasts/reset-password-bundle), see docs/erklaerungen/08-login-und-sessions.md.
 */
final class PasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/passwort-vergessen', name: 'admin_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, PasswordLinkMailer $linkMailer): Response
    {
        $form = $this->createForm(PasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = \is_string($email) ? $users->loadUserByIdentifier($email) : null;

            // Same answer whether the account exists, the mail went out or the last link is too recent:
            // otherwise the page would tell which addresses have an account
            if (null !== $user) {
                try {
                    $this->setTokenObjectInSession($linkMailer->sendResetLink($user));
                } catch (ResetPasswordExceptionInterface) {
                }
            }

            return $this->redirectToRoute('admin_password_check_email');
        }

        return $this->render('admin/password/request.html.twig', ['form' => $form]);
    }

    #[Route('/admin/passwort-vergessen/gesendet', name: 'admin_password_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        // A fake token when no mail was sent, so this page looks the same in every case
        $resetToken = $this->getTokenObjectFromSession() ?? $this->resetPasswordHelper->generateFakeResetToken();

        return $this->render('admin/password/check_email.html.twig', ['resetToken' => $resetToken]);
    }

    #[Route('/admin/passwort-setzen/{token}', name: 'admin_password_reset', methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, ?string $token = null): Response
    {
        if (null !== $token) {
            // Move the token from the URL into the session, so it does not stay in the address bar or
            // leak through the Referer header to other sites
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('admin_password_reset');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No password link in the URL or in the session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            // Expired, already used or unknown: one message for all, a new link fixes each case
            $this->addFlash('danger', 'password.flash.link_invalid');

            return $this->redirectToRoute('admin_password_request');
        }
        \assert($user instanceof User);

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A link works only once
            $this->resetPasswordHelper->removeResetRequest($token);

            $password = $form->get('newPassword')->getData();
            \assert(\is_string($password));
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $this->entityManager->flush();
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'password.flash.set');

            return $this->redirectToRoute('admin_login');
        }

        return $this->render('admin/password/reset.html.twig', ['form' => $form]);
    }
}
