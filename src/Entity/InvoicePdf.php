<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoicePdfRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The PDF of an invoice, created once together with the invoice and never changed (datenmodell.md 3.7,
 * umsetzung-admin.md D2a). Stored in the database, so a database backup contains everything.
 *
 * Own table instead of a column on invoice, so that lists of invoices do not load the PDFs. The relation is
 * unidirectional on purpose: Invoice does not know its PDF. Doctrine cannot load the inverse side of a one-to-one
 * relation lazily, so a property Invoice::$pdf would load the PDF with every invoice again.
 */
#[ORM\Entity(repositoryClass: InvoicePdfRepository::class)]
class InvoicePdf
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Doctrine's blob type (bytea in PostgreSQL) accepts a string but returns a stream resource when loaded.
     *
     * @var string|resource
     */
    #[ORM\Column(type: Types::BLOB)]
    private mixed $content;

    public function __construct(
        // One-to-one: Doctrine adds a unique constraint on invoice_id
        #[ORM\OneToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Invoice $invoice,
        string $content,
    ) {
        $this->content = $content;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    /**
     * The PDF as a string, whether it was just created (string) or loaded from the database (stream).
     */
    public function getContent(): string
    {
        $content = $this->content;
        if (!\is_string($content)) {
            rewind($content);
            $content = (string) stream_get_contents($content);
            // Keep the string: a stream can be read only once without rewinding
            $this->content = $content;
        }

        return $content;
    }
}
