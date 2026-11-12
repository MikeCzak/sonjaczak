<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ContactInquiryType;
use App\Inquiry\ContactInquiry;
use App\Inquiry\InquiryProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contact page with general inquiry form and the imprint.
 */
final class ContactController extends AbstractController
{
    use InquiryFormTrait;

    #[Route('/kontakt', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request, InquiryProcessor $inquiries): Response
    {
        return $this->handleInquiry($request, $inquiries, ContactInquiryType::class, new ContactInquiry(), 'contact', 'contact/index.html.twig');
    }
}
