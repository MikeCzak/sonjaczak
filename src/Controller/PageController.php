<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages without forms.
 */
final class PageController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }

    #[Route('/ueber-mich', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/datenschutz', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }

    /**
     * Information under Art. 13 GDPR for students and parents (umsetzung-admin.md F).
     */
    #[Route('/datenschutz/unterricht', name: 'app_privacy_lessons', methods: ['GET'])]
    public function privacyLessons(): Response
    {
        return $this->render('page/privacy_lessons.html.twig');
    }
}
