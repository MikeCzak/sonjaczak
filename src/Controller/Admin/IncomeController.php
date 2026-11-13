<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Invoice\IncomeCsv;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Income per year by date of payment (step D5): overview and CSV export for the income statement.
 */
#[IsGranted(User::ROLE_ADMIN)]
final class IncomeController extends AbstractController
{
    #[Route('/admin/einnahmen', name: 'admin_income_index', methods: ['GET'])]
    public function index(PaymentRepository $payments, #[MapQueryParameter] ?int $jahr = null): Response
    {
        $years = $payments->years();
        $year = $jahr ?? $years[0] ?? (int) date('Y');
        $list = $payments->findByYear($year);

        return $this->render('admin/income/index.html.twig', [
            'payments' => $list,
            'total' => array_sum(array_map(static fn ($payment): int => $payment->getAmountCents(), $list)),
            'year' => $year,
            'years' => $years,
        ]);
    }

    #[Route('/admin/einnahmen/{year}.csv', name: 'admin_income_csv', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function csv(int $year, PaymentRepository $payments, IncomeCsv $csv): Response
    {
        return new Response($csv->build($payments->findByYear($year)), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('Einnahmen_%d.csv', $year)),
            // Personal data: never stored by the browser cache
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
