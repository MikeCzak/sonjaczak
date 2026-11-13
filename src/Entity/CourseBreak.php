<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\BreakSource;
use App\Repository\CourseBreakRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Holidays, a public holiday or another day without lessons, for the whole course (datenmodell.md 3.3).
 * A single day has the same first and last day.
 */
#[ORM\Entity(repositoryClass: CourseBreakRepository::class)]
class CourseBreak
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Owning side. Not nullable: a break without course makes no sense.
     */
    #[ORM\ManyToOne(inversedBy: 'breaks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $label = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $firstDay = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'firstDay', message: 'course_break.ends_before_start')]
    private ?\DateTimeImmutable $lastDay = null;

    #[ORM\Column(length: 10, enumType: BreakSource::class)]
    private BreakSource $source = BreakSource::Manual;

    public function __construct(string $label = '', ?\DateTimeImmutable $firstDay = null, ?\DateTimeImmutable $lastDay = null, BreakSource $source = BreakSource::Manual)
    {
        $this->label = $label;
        $this->firstDay = $firstDay;
        $this->lastDay = $lastDay ?? $firstDay;
        $this->source = $source;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getFirstDay(): ?\DateTimeImmutable
    {
        return $this->firstDay;
    }

    public function setFirstDay(?\DateTimeImmutable $firstDay): static
    {
        $this->firstDay = $firstDay;

        return $this;
    }

    public function getLastDay(): ?\DateTimeImmutable
    {
        return $this->lastDay;
    }

    public function setLastDay(?\DateTimeImmutable $lastDay): static
    {
        $this->lastDay = $lastDay;

        return $this;
    }

    public function getSource(): BreakSource
    {
        return $this->source;
    }

    /**
     * Whether this break lies completely inside the other one.
     */
    public function isWithin(self $other): bool
    {
        return null !== $this->firstDay && null !== $this->lastDay
            && $other->contains($this->firstDay) && $other->contains($this->lastDay);
    }

    /**
     * Compares the calendar day only ('Y-m-d'), so a time of day in $day does not matter.
     */
    public function contains(\DateTimeImmutable $day): bool
    {
        if (null === $this->firstDay || null === $this->lastDay) {
            return false;
        }
        $date = $day->format('Y-m-d');

        return $date >= $this->firstDay->format('Y-m-d') && $date <= $this->lastDay->format('Y-m-d');
    }
}
