<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

/**
 * Invoice recipient, copied when the invoice is created ("snapshot", datenmodell.md 3.7): an invoice is a receipt
 * and must not change when the student's or guardian's address is corrected later. Hence no setters, and the address
 * is copied instead of shared.
 *
 * Embeddables may contain embeddables: the columns are recipient_name, recipient_address_street and so on.
 */
#[ORM\Embeddable]
final class Recipient
{
    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Embedded(class: Address::class)]
    private Address $address;

    public function __construct(string $name, Address $address)
    {
        $this->name = $name;
        $this->address = clone $address;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * A copy, so that changing it cannot change the invoice.
     */
    public function getAddress(): Address
    {
        return clone $this->address;
    }
}
