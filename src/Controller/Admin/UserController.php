<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use App\Security\PasswordLinkMailer;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;

/**
 * User management for ROLE_SUPER_ADMIN (docs/planung/rollen.md, step B4): admin accounts with invitation link.
 * Accounts are deactivated, never deleted, so it stays visible who had access.
 */
#[IsGranted(User::ROLE_SUPER_ADMIN)]
#[Route('/admin/benutzer')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordLinkMailer $linkMailer,
    ) {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/neu', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(UserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            \assert($user instanceof User);
            $user->setRoles([$this->submittedRole($form->get('role')->getData())]);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->sendInvitation($user);

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $canChangeAccess = $this->isGranted(UserVoter::CHANGE_ACCESS, $user);
        $form = $this->createForm(UserType::class, $user, [
            'role' => $user->getAdminRole() ?? User::ROLE_ADMIN,
            'can_change_role' => $canChangeAccess,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($canChangeAccess) {
                $user->setRoles([$this->submittedRole($form->get('role')->getData())]);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'user.flash.saved');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'can_change_access' => $canChangeAccess,
        ]);
    }

    #[Route('/{id}/einladen', name: 'admin_user_invite', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserVoter::CHANGE_ACCESS, 'user')]
    public function invite(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('user-access-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $user->activate();
            $this->entityManager->flush();
            $this->sendInvitation($user);
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/deaktivieren', name: 'admin_user_deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserVoter::CHANGE_ACCESS, 'user')]
    public function deactivate(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('user-access-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $user->deactivate();
            $this->entityManager->flush();
            $this->addFlash('success', 'user.flash.deactivated');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    private function sendInvitation(User $user): void
    {
        try {
            $this->linkMailer->sendInvitation($user);
            $this->addFlash('success', 'user.flash.invited');
        } catch (TooManyPasswordRequestsException) {
            // A link younger than throttle_limit (config/packages/reset_password.yaml) is still valid
            $this->addFlash('warning', 'user.flash.invitation_throttled');
        }
    }

    private function submittedRole(mixed $role): string
    {
        return \in_array($role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true) ? $role : User::ROLE_ADMIN;
    }
}
