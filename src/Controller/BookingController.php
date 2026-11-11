<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Booking pages: overview, Sonja solo (inquiry form follows) and the band.
 */
#[Route('/booking')]
final class BookingController extends AbstractController
{
    #[Route('', name: 'app_booking', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('booking/index.html.twig');
    }

    #[Route('/solo', name: 'app_booking_solo', methods: ['GET'])]
    public function solo(): Response
    {
        return $this->render('booking/solo.html.twig');
    }

    #[Route('/band', name: 'app_booking_band', methods: ['GET'])]
    public function band(): Response
    {
        return $this->render('booking/band.html.twig');
    }
}
