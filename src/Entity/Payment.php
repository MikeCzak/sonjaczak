<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PaymentMethod;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A payment towards an invoice (datenmodell.md 3.8). An invoice can be paid in parts; whether it is paid is
 * calculated from the sum of its payments. paidOn decides the year of the income (cash basis), so it is required.
 * Recorded in the admin area from step D3 on.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false)]
        private Invoice $invoice,
        #[ORM\Column]
        private int $amountCents,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $paidOn,
        #[ORM\Column(length: 20, enumType: PaymentMethod::class)]
        private PaymentMethod $method,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getPaidOn(): \DateTimeImmutable
    {
        return $this->paidOn;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }
}
