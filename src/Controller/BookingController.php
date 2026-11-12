<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\BookingInquiryType;
use App\Inquiry\BookingInquiry;
use App\Inquiry\InquiryProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Booking pages: overview, Sonja solo with inquiry form, and the band.
 */
#[Route('/booking')]
final class BookingController extends AbstractController
{
    use InquiryFormTrait;

    #[Route('', name: 'app_booking', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('booking/index.html.twig');
    }

    #[Route('/solo', name: 'app_booking_solo', methods: ['GET', 'POST'])]
    public function solo(Request $request, InquiryProcessor $inquiries): Response
    {
        return $this->handleInquiry($request, $inquiries, BookingInquiryType::class, new BookingInquiry(), 'booking_solo', 'booking/solo.html.twig');
    }

    #[Route('/band', name: 'app_booking_band', methods: ['GET'])]
    public function band(): Response
    {
        return $this->render('booking/band.html.twig');
    }
}
