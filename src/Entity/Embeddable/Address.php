<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Postal address, embedded in Student and Guardian (and later copied into the invoice recipient).
 * No table of its own: the columns land in the table of the embedding entity, e.g. address_street
 * (docs/planung/datenmodell.md 3.2.1).
 *
 * All columns are nullable: Doctrine never leaves an embeddable null, an unknown address is an Address with empty
 * fields. Whether an address is complete matters for invoices only (isComplete()).
 */
#[ORM\Embeddable]
final class Address
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $street = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $country = null;

    public function __construct(?string $street = null, ?string $postalCode = null, ?string $city = null, ?string $country = null)
    {
        $this->street = $street;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): void
    {
        $this->street = $street;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    /**
     * Empty means Austria; only addresses abroad need a country.
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    /**
     * Enough for a letter: street, postal code and city.
     */
    public function isComplete(): bool
    {
        return null !== $this->street && null !== $this->postalCode && null !== $this->city;
    }
}
