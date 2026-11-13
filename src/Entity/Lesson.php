<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\LessonStatus;
use App\Repository\LessonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One lesson of an enrollment (docs/planung/datenmodell.md 3.5). Generated as "planned" (App\Lesson\LessonPlanner),
 * then marked by Sonja.
 */
#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\Index(name: 'idx_lesson_starts_at', fields: ['startsAt'])]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private Enrollment $enrollment;

    /**
     * Date and time of this one lesson: moving it or a double lesson does not change the enrollment's time slot.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 10, max: 180)]
    private int $durationMinutes;

    #[ORM\Column(length: 30, enumType: LessonStatus::class)]
    private LessonStatus $status = LessonStatus::Planned;

    /**
     * What was done ("Runaway") or why it was cancelled ("krank"); the status tells which.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /**
     * Was a trial lesson, taken over into the course (step C5).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $fromTrial = false;

    /**
     * For a make-up lesson: the lesson Sonja cancelled. Self-reference (lesson → lesson), nullable.
     * SET NULL: if the cancelled lesson is deleted (e.g. with its enrollment), the make-up lesson only loses the
     * reference instead of blocking the delete. Nothing else is deleted with it.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Lesson $makeUpFor = null;

    public function __construct(Enrollment $enrollment, \DateTimeImmutable $startsAt, ?int $durationMinutes = null)
    {
        $this->enrollment = $enrollment;
        $this->startsAt = $startsAt;
        $this->durationMinutes = $durationMinutes ?? $enrollment->getLessonMinutes();
        $enrollment->addLesson($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
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

    public function getStatus(): LessonStatus
    {
        return $this->status;
    }

    public function setStatus(LessonStatus $status): static
    {
        $this->status = $status;

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

    public function isFromTrial(): bool
    {
        return $this->fromTrial;
    }

    public function setFromTrial(bool $fromTrial): static
    {
        $this->fromTrial = $fromTrial;

        return $this;
    }

    public function getMakeUpFor(): ?Lesson
    {
        return $this->makeUpFor;
    }

    public function setMakeUpFor(?Lesson $makeUpFor): static
    {
        $this->makeUpFor = $makeUpFor;

        return $this;
    }
}
