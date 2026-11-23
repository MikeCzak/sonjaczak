<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\System\OpcacheStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Technical values of the running server, only for the super admin (docs/planung/wartung.md W4).
 * Runs inside the FrankenPHP worker, so it sees the real OPcache; a console command would not.
 */
#[IsGranted(User::ROLE_SUPER_ADMIN)]
#[Route('/admin/system')]
final class SystemController extends AbstractController
{
    #[Route('', name: 'admin_system', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/system/index.html.twig', [
            'opcache' => OpcacheStatus::read(),
            'phpVersion' => \PHP_VERSION,
            'sapi' => \PHP_SAPI,
        ]);
    }
}
