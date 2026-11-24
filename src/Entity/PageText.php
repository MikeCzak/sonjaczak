<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageTextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A page text changed by Sonja in the admin area (docs/planung/cms.md K1). Without a row, the text from
 * translations/content.de.yaml applies (App\Content\PageTextTranslator).
 */
#[ORM\Entity(repositoryClass: PageTextRepository::class)]
class PageText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Translation key in the domain "content", e.g. lessons.prices.body.
     */
    #[ORM\Column(length: 190, unique: true)]
    private string $textKey;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * Who saved the text last; null once that account is deleted.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct(string $textKey, string $body, \DateTimeImmutable $updatedAt, ?User $updatedBy)
    {
        $this->textKey = $textKey;
        $this->body = $body;
        $this->updatedAt = $updatedAt;
        $this->updatedBy = $updatedBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTextKey(): string
    {
        return $this->textKey;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function change(string $body, \DateTimeImmutable $updatedAt, ?User $updatedBy): void
    {
        $this->body = $body;
        $this->updatedAt = $updatedAt;
        $this->updatedBy = $updatedBy;
    }
}
