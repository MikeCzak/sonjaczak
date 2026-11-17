<?php

declare(strict_types=1);

namespace App\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Converts HTML into an A4 PDF with dompdf, with the same safe options for every PDF of the site: invoices
 * (App\Invoice\InvoicePdfRenderer) and the privacy notice for lessons (umsetzung-admin.md F2).
 *
 * dompdf has no Symfony recipe and is not a service: it is created here for each PDF (docs/erklaerungen/04-composer.md,
 * "Wenn es kein Rezept gibt"). Layout without flexbox or grid, which dompdf does not support.
 */
final readonly class HtmlToPdf
{
    /**
     * @throws PdfException if dompdf fails or returns nothing
     */
    public function convert(string $html): string
    {
        try {
            $dompdf = new Dompdf($this->options());
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            $pdf = $dompdf->output();
        } catch (\Exception $exception) {
            throw new PdfException('dompdf could not render the PDF.', previous: $exception);
        }

        if ('' === $pdf) {
            throw new PdfException('dompdf returned no PDF.');
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
