<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Entity\User;
use App\Form\Admin\InvoiceType;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invoices (step D1): list per year, details, creating one from an enrollment. There is no edit or delete: an
 * invoice is a receipt (datenmodell.md 3.7).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class InvoiceController extends AbstractController
{
    #[Route('/admin/honorarnoten', name: 'admin_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoices, #[MapQueryParameter] ?int $jahr = null): Response
    {
        $years = $invoices->years();
        $year = $jahr ?? $years[0] ?? (int) date('Y');

        return $this->render('admin/invoice/index.html.twig', [
            'invoices' => $invoices->findByYear($year),
            'year' => $year,
            'years' => $years,
        ]);
    }

    #[Route('/admin/honorarnoten/{id}', name: 'admin_invoice_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('admin/invoice/show.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/admin/teilnahmen/{id}/honorarnote', name: 'admin_invoice_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Request $request, Enrollment $enrollment, InvoiceIssuer $issuer, TranslatorInterface $translator): Response
    {
        $problems = $issuer->problems($enrollment);
        $course = $enrollment->getCourse();
        $draft = new InvoiceDraft(
            $translator->trans('invoice.default_description', [
                '%term%' => $translator->trans($course->getTerm()->labelKey(), ['%year%' => $course->getYearLabel()], 'admin'),
            ], 'admin'),
            new \DateTimeImmutable('today'),
        );
        $form = $this->createForm(InvoiceType::class, $draft);
        $form->handleRequest($request);

        if ([] === $problems && $form->isSubmitted() && $form->isValid()) {
            $invoice = $issuer->issue($enrollment, $draft);
            $this->addFlash('success', 'invoice.flash.created');

            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('admin/invoice/new.html.twig', [
            'enrollment' => $enrollment,
            'form' => $form,
            'problems' => $problems,
            'recipient' => $issuer->recipientFor($enrollment),
        ]);
    }
}
