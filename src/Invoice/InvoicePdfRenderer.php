<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Entity\Invoice;
use App\Pdf\HtmlToPdf;
use App\Repository\SettingsRepository;
use Twig\Environment;

/**
 * Renders the PDF of an invoice or credit note (umsetzung-admin.md D2b): HTML from Twig, converted by dompdf
 * (App\Pdf\HtmlToPdf).
 *
 * Not final, so that a test can replace it with one that fails (InvoicePdfTest).
 */
readonly class InvoicePdfRenderer
{
    public function __construct(
        private Environment $twig,
        private SettingsRepository $settings,
        private HtmlToPdf $htmlToPdf,
    ) {
    }

    /**
     * The HTML that becomes the PDF; separate so that tests can check the text (the PDF itself is compressed).
     */
    public function renderHtml(Invoice $invoice): string
    {
        return $this->twig->render('admin/invoice/pdf.html.twig', [
            'invoice' => $invoice,
            'settings' => $this->settings->current(),
        ]);
    }

    /**
     * @throws InvoicePdfException if Twig or dompdf fails
     */
    public function render(Invoice $invoice): string
    {
        try {
            return $this->htmlToPdf->convert($this->renderHtml($invoice));
        } catch (\Exception $exception) {
            throw new InvoicePdfException('The invoice PDF could not be rendered.', previous: $exception);
        }
    }
}
