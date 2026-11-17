<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Entity\Invoice;
use App\Repository\SettingsRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Renders the PDF of an invoice or credit note (umsetzung-admin.md D2b): HTML from Twig, converted by dompdf.
 *
 * dompdf has no Symfony recipe and is not a service: it is created here for each PDF (docs/erklaerungen/04-composer.md,
 * "Wenn es kein Rezept gibt"). Layout without flexbox or grid, which dompdf does not support.
 *
 * Not final, so that a test can replace it with one that fails (InvoicePdfTest).
 */
readonly class InvoicePdfRenderer
{
    public function __construct(
        private Environment $twig,
        private SettingsRepository $settings,
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
            $dompdf = new Dompdf($this->options());
            $dompdf->loadHtml($this->renderHtml($invoice), 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            $pdf = $dompdf->output();
        } catch (\Exception $exception) {
            throw new InvoicePdfException('The invoice PDF could not be rendered.', previous: $exception);
        }

        if ('' === $pdf) {
            throw new InvoicePdfException('dompdf returned no PDF.');
        }

        return $pdf;
    }

    /**
     * Defaults from vendor/dompdf/dompdf/src/Options.php; set here are only the ones that differ or matter.
     *
     * Setters instead of an array for the constructor: Options::set() silently ignores unknown keys, a typo would
     * never show.
     */
    private function options(): Options
    {
        $options = new Options();
        // Default "letter" (US)
        $options->setDefaultPaperSize('a4');
        // The built-in PDF fonts (Helvetica …) only know Windows ANSI; DejaVu comes with dompdf and has €, ß, ä
        $options->setDefaultFont('DejaVu Sans');
        // Already the defaults, set explicitly because they matter for security: no files or URLs from the HTML are
        // fetched, no PHP in the HTML is run
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        // Default true: would put <script> from the HTML into the PDF as JavaScript; there is none, so off
        $options->setIsJavascriptEnabled(false);

        return $options;
    }
}
