<?php

declare(strict_types=1);

namespace App\Invoice;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What Sonja enters when creating an invoice (form data). Everything else (number, amount, recipient) is taken
 * from the enrollment by InvoiceIssuer. A separate class because the Invoice itself has no setters.
 */
final class InvoiceDraft
{
    #[Assert\NotNull]
    public ?\DateTimeImmutable $issuedOn;

    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'issuedOn', message: 'invoice.due_before_issue')]
    public ?\DateTimeImmutable $dueOn = null;

    /**
     * Empty: no instalments.
     */
    #[Assert\GreaterThan(propertyPath: 'dueOn', message: 'invoice.second_before_first')]
    public ?\DateTimeImmutable $secondInstallmentDueOn = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $description;

    public function __construct(string $description, \DateTimeImmutable $issuedOn)
    {
        $this->description = $description;
        $this->issuedOn = $issuedOn;
    }
}
