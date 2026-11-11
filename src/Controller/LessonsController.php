<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Singing lessons: information and inquiry form (follows).
 */
final class LessonsController extends AbstractController
{
    #[Route('/unterricht', name: 'app_lessons', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('lessons/index.html.twig');
    }
}
