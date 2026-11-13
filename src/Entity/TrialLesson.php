<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrialLessonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A free trial lesson (docs/planung/datenmodell.md 3.6). Not part of a course: no make-up lessons, no price, does not
 * count towards plannedLessons. Becomes a held Lesson when the student enrolls (App\Lesson\TrialTakeover).
 */
#[ORM\Entity(repositoryClass: TrialLessonRepository::class)]
#[ORM\Index(name: 'idx_trial_lesson_starts_at', fields: ['startsAt'])]
class TrialLesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Owning side. Deleted together with the student (cascade on Student::$trialLessons): a trial lesson is no
     * receipt, and a person who does not stay is deleted with everything (datenmodell.md 5).
     */
    #[ORM\ManyToOne(inversedBy: 'trialLessons')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Student $student = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 10, max: 180)]
    private int $durationMinutes = 50;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
