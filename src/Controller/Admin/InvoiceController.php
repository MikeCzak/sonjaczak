<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Form\Admin\CreditType;
use App\Form\Admin\InvoiceType;
use App\Form\Admin\PaymentType;
use App\Invoice\CreditDraft;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Invoice\PaymentDraft;
use App\Repository\InvoiceRepository;
use App\Security\Voter\InvoiceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invoices (steps D1, D3): list per year, details with payments, creating one from an enrollment, credit notes.
 * There is no edit or delete: an invoice is a receipt (datenmodell.md 3.7). Payments can be deleted, they are
 * Sonja's own records of money received, not receipts.
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

    /**
     * Details and, while something is open, the form for a payment (or, after a credit, for the refund). The form is
     * prefilled with the open amount and today: "paid in full today" is one click, date and amount can be changed.
     */
    #[Route('/admin/honorarnoten/{id}', name: 'admin_invoice_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        $open = $invoice->getOpenCents();
        $form = null;
        if (0 !== $open) {
            $draft = new PaymentDraft(abs($open), new \DateTimeImmutable('today'));
            $form = $this->createForm(PaymentType::class, $draft);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid() && null !== $draft->amountCents && null !== $draft->paidOn) {
                $refund = $open < 0;
                $entityManager->persist(new Payment(
                    $this->paymentTarget($invoice, $refund),
                    $refund ? -$draft->amountCents : $draft->amountCents,
                    $draft->paidOn,
                    $draft->method,
                ));
                $entityManager->flush();
                $this->addFlash('success', $refund ? 'payment.flash.refund_saved' : 'payment.flash.saved');

                return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
            }
        }

        return $this->render('admin/invoice/show.html.twig', ['invoice' => $invoice, 'payment_form' => $form]);
    }

    #[Route('/admin/honorarnoten/{id}/gutschrift', name: 'admin_invoice_credit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(InvoiceVoter::CREDIT, 'invoice')]
    public function credit(Request $request, Invoice $invoice, InvoiceIssuer $issuer, TranslatorInterface $translator): Response
    {
        $draft = new CreditDraft(
            $translator->trans('credit.default_description', ['%number%' => $invoice->getDisplayNumber()], 'admin'),
            new \DateTimeImmutable('today'),
            $invoice->getCreditableCents(),
        );
        $form = $this->createForm(CreditType::class, $draft);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note = $issuer->credit($invoice, $draft);
            $this->addFlash('success', 'credit.flash.created');

            return $this->redirectToRoute('admin_invoice_show', ['id' => $note->getId()]);
        }

        return $this->render('admin/invoice/credit.html.twig', ['invoice' => $invoice, 'form' => $form]);
    }

    #[Route('/admin/zahlungen/{id}/loeschen', name: 'admin_payment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePayment(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        $invoice = $payment->getInvoice();
        if ($this->isCsrfTokenValid('delete-payment-'.$payment->getId(), $request->getPayload()->getString('_token'))) {
            $invoice->getPayments()->removeElement($payment);
            $entityManager->remove($payment);
            $entityManager->flush();
            $this->addFlash('success', 'payment.flash.deleted');
        }

        return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getOriginal()->getId()]);
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

    /**
     * Payments go to the original invoice; a refund goes to the credit note shown, or else to the latest one.
     */
    private function paymentTarget(Invoice $invoice, bool $refund): Invoice
    {
        if (!$refund || $invoice->isCreditNote()) {
            return $refund ? $invoice : $invoice->getOriginal();
        }

        $notes = $invoice->getOriginal()->getCreditNotes();

        return false === $notes->last() ? $invoice : $notes->last();
    }
}
