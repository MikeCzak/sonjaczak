<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ContactInquiryType;
use App\Inquiry\ContactInquiry;
use App\Inquiry\InquiryProcessor;
use App\Inquiry\InquiryStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contact page with general inquiry form and the imprint.
 */
final class ContactController extends AbstractController
{
    #[Route('/kontakt', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request, InquiryProcessor $inquiries): Response
    {
        $inquiry = new ContactInquiry();
        $form = $this->createForm(ContactInquiryType::class, $inquiry);
        $form->handleRequest($request);

        $status = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $status = $inquiries->process($inquiry, 'contact', $request);

            // Post/Redirect/Get; the query parameter shows the confirmation without a session (no cookie)
            if (InquiryStatus::Sent === $status) {
                return $this->redirectToRoute('app_contact', ['gesendet' => 1, '_fragment' => 'anfrage'], Response::HTTP_SEE_OTHER);
            }
        }

        // An invalid form turns the status into 422 (AbstractController::render())
        return $this->render('contact/index.html.twig', [
            'form' => $form,
            'inquiry_status' => $status,
            'inquiry_sent' => $request->query->has('gesendet'),
        ], new Response(status: $status?->httpStatus() ?? Response::HTTP_OK));
    }
}
