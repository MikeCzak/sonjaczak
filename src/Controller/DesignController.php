<?php

declare(strict_types=1);

namespace App\Controller;

use App\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Style guide for comparing the themes (App\Theme\Theme). Not linked anywhere, excluded from search engines.
 */
final class DesignController extends AbstractController
{
    #[Route('/_design', name: 'app_design', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('design/index.html.twig', [
            'themes' => Theme::cases(),
        ]);
    }
}
