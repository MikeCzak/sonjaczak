<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Style guide for comparing the design variants (step 3). Not linked anywhere, excluded from search engines.
 */
final class DesignController extends AbstractController
{
    /**
     * Script fonts that are candidates for the logo. Font family names are data, not translatable text.
     */
    private const array LOGO_FONTS = ['Great Vibes', 'Allura', 'Parisienne', 'Dancing Script'];

    #[Route('/_design', name: 'app_design', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('design/index.html.twig', [
            'logo_fonts' => self::LOGO_FONTS,
        ]);
    }
}
