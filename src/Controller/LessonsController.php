<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LessonsInquiryType;
use App\Inquiry\InquiryProcessor;
use App\Inquiry\LessonsInquiry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Singing lessons: information and inquiry form (follows).
 */
final class LessonsController extends AbstractController
{
    use InquiryFormTrait;

    #[Route('/unterricht', name: 'app_lessons', methods: ['GET', 'POST'])]
    public function index(Request $request, InquiryProcessor $inquiries): Response
    {
        return $this->handleInquiry($request, $inquiries, LessonsInquiryType::class, new LessonsInquiry(), 'lessons', 'lessons/index.html.twig');
    }
}
