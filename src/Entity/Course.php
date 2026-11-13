<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\Term;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A term of singing lessons, e.g. Wintersemester 2026/27 in room 1.12 (docs/planung/datenmodell.md 3.3).
 * Price, lesson length and number of lessons are defaults for new enrollments.
 */
#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: Term::class)]
    private Term $term = Term::Winter;

    /**
     * Year the term starts in: 2026 for Wintersemester 2026/27.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 2020, max: 2100)]
    private int $year;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startsOn', message: 'course.ends_before_start')]
    private ?\DateTimeImmutable $endsOn = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $room = null;

    /**
     * Price for the term in cents: integers, so no rounding errors as with floats (495,00 € = 49500).
     */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priceCents = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 40)]
    private int $plannedLessons = 15;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 10, max: 180)]
    private int $lessonMinutes = 50;

    /**
     * Holidays and other days without lessons. Part of the course: saved and deleted with it (cascade), and a break
     * removed from this collection is deleted (orphanRemoval). The one place with cascades in the domain model,
     * see docs/erklaerungen/10-datenbank-kurse.md.
     *
     * @var Collection<int, CourseBreak>
     */
    #[ORM\OneToMany(targetEntity: CourseBreak::class, mappedBy: 'course', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['firstDay' => 'ASC'])]
    private Collection $breaks;

    public function __construct()
    {
        $this->year = (int) date('Y');
        $this->breaks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTerm(): Term
    {
        return $this->term;
    }

    public function setTerm(Term $term): static
    {
        $this->term = $term;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    /**
     * "2026/27" or "2027", for the title together with the translated term.
     */
    public function getYearLabel(): string
    {
        return $this->term->yearLabel($this->year);
    }

    public function getStartsOn(): ?\DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function setStartsOn(?\DateTimeImmutable $startsOn): static
    {
        $this->startsOn = $startsOn;

        return $this;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function setEndsOn(?\DateTimeImmutable $endsOn): static
    {
        $this->endsOn = $endsOn;

        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setRoom(?string $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getPlannedLessons(): int
    {
        return $this->plannedLessons;
    }

    public function setPlannedLessons(int $plannedLessons): static
    {
        $this->plannedLessons = $plannedLessons;

        return $this;
    }

    public function getLessonMinutes(): int
    {
        return $this->lessonMinutes;
    }

    public function setLessonMinutes(int $lessonMinutes): static
    {
        $this->lessonMinutes = $lessonMinutes;

        return $this;
    }

    /**
     * @return Collection<int, CourseBreak>
     */
    public function getBreaks(): Collection
    {
        return $this->breaks;
    }

    /**
     * Sets both sides: CourseBreak::$course is the owning side, the collection is only kept in sync for this request.
     */
    public function addBreak(CourseBreak $break): static
    {
        if (!$this->breaks->contains($break)) {
            $this->breaks->add($break);
            $break->setCourse($this);
        }

        return $this;
    }

    public function removeBreak(CourseBreak $break): static
    {
        $this->breaks->removeElement($break);

        return $this;
    }

    /**
     * Whether a day falls into one of the breaks (used when lessons are generated, step C3).
     */
    public function isBreakDay(\DateTimeImmutable $day): bool
    {
        foreach ($this->breaks as $break) {
            if ($break->contains($day)) {
                return true;
            }
        }

        return false;
    }
}
