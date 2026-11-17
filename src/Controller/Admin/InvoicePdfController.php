<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoicePdfRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Download of the stored invoice PDFs (umsetzung-admin.md D2d): one at a time, or all of a course as a ZIP. The PDFs
 * are never rendered here, only delivered as they were created with the invoice. Payments do not matter for the
 * download: the invoice is the request for payment (datenmodell.md 3.7).
 */
#[IsGranted(User::ROLE_ADMIN)]
final class InvoicePdfController extends AbstractController
{
    public function __construct(private readonly InvoicePdfRepository $pdfs)
    {
    }

    #[Route('/admin/honorarnoten/{id}/pdf', name: 'admin_invoice_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Invoice $invoice): Response
    {
        // Only invoices from before step D2 could lack a PDF; production has none
        $pdf = $this->pdfs->findForInvoice($invoice) ?? throw $this->createNotFoundException('The invoice has no PDF.');

        return $this->attachment($pdf->getContent(), $invoice->getFileName(), 'application/pdf');
    }

    /**
     * All invoices and credit notes of the course in one ZIP, e.g. "Honorarnoten_GU_WiSe_2026.zip". Built in a
     * temporary file because ZipArchive cannot write to memory, then sent as a normal response: with a few PDFs of
     * about 25 KB that is small, and the temporary file is gone before the response leaves.
     */
    #[Route('/admin/kurse/{id}/honorarnoten.zip', name: 'admin_course_invoices_zip', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function zip(Course $course): Response
    {
        $pdfs = $this->pdfs->findForCourse($course);
        if ([] === $pdfs) {
            throw $this->createNotFoundException('The course has no invoices.');
        }

        $path = tempnam(sys_get_temp_dir(), 'invoices');
        if (false === $path) {
            throw new \RuntimeException('Could not create a temporary file.');
        }

        try {
            $zip = new \ZipArchive();
            if (true !== $zip->open($path, \ZipArchive::OVERWRITE)) {
                throw new \RuntimeException('Could not create the ZIP file.');
            }
            foreach ($pdfs as $pdf) {
                $zip->addFromString($pdf->getInvoice()->getFileName(), $pdf->getContent());
            }
            $zip->close();
            $content = (string) file_get_contents($path);
        } finally {
            unlink($path);
        }

        $name = \sprintf('Honorarnoten_GU_%s_%d.zip', $course->getTerm()->abbreviation(), $course->getYear());

        return $this->attachment($content, $name, 'application/zip');
    }

    /**
     * A download with the file name in the header. Umlauts stay in the name (filename*, RFC 6266); older programs get
     * an ASCII fallback ("Müßig" → "Muessig").
     */
    private function attachment(string $content, string $name, string $type): Response
    {
        $extension = pathinfo($name, \PATHINFO_EXTENSION);
        $fallback = (new AsciiSlugger('de'))->slug(pathinfo($name, \PATHINFO_FILENAME), '_').'.'.$extension;

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
        ]);
    }
}
