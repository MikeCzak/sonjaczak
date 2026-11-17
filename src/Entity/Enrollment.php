<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\Billing;
use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A student taking part in a course, with a weekly time slot (docs/planung/datenmodell.md 3.4).
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
// Nobody takes the same course twice: checked by the database, and before by the validator with a readable message
#[ORM\UniqueConstraint(name: 'uniq_enrollment_course_student', fields: ['course', 'student'])]
#[UniqueEntity(fields: ['course', 'student'], message: 'enrollment.already_enrolled', errorPath: 'student')]
class Enrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $course;

    /**
     * Null only after the student's personal data was deleted while an invoice must still be kept
     * (App\Privacy\PersonalDataPurger, umsetzung-admin.md F3): the enrollment then only links course and invoices.
     * The form still requires a student. PostgreSQL treats NULLs as distinct, so the unique constraint allows several.
     */
    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotNull]
    private ?Student $student = null;

    /**
     * ISO day of the week, 1 = Monday … 7 = Sunday (as PHP's date('N')).
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7)]
    private int $weekday = 5;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 10, max: 180)]
    private int $lessonMinutes;

    /**
     * Copied from the course when created, then independent: a discount changes only this enrollment.
     */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priceCents;

    /**
     * How many lessons Sonja owes (App\Lesson\MakeUpRule) and the planner keeps (App\Lesson\LessonPlanner). Copied
     * from the course when created, then independent like the price: lowered when lessons are dropped without
     * replacement or the enrollment ends early (umsetzung-admin.md C7). 0 = ended before the course began.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 40)]
    private int $plannedLessons;

    /**
     * Why plannedLessons differs from the course (C7: required when Sonja changes it).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $plannedLessonsReason = null;

    #[ORM\Column(length: 20, enumType: Billing::class)]
    private Billing $billing = Billing::Semester;

    #[ORM\Column]
    private bool $billToGuardian = false;

    /**
     * The lessons belong to the enrollment: saved and deleted with it (like the breaks of a course).
     *
     * @var Collection<int, Lesson>
     */
    #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'enrollment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $lessons;

    /**
     * Inverse side, no cascade: invoices are receipts and are never deleted with the enrollment
     * (App\Security\Voter\EnrollmentVoter prevents deleting an enrollment that has invoices).
     *
     * @var Collection<int, Invoice>
     */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'enrollment')]
    #[ORM\OrderBy(['issuedOn' => 'ASC', 'id' => 'ASC'])]
    private Collection $invoices;

    /**
     * Takes price, lesson length and planned number of lessons from the course; the invoice goes to the guardian if the student is a minor
     * at the start of the course (datenmodell.md 3.4), Sonja can change both.
     */
    public function __construct(Course $course, ?Student $student = null)
    {
        $this->course = $course;
        $this->lessonMinutes = $course->getLessonMinutes();
        $this->priceCents = $course->getPriceCents();
        $this->plannedLessons = $course->getPlannedLessons();
        $this->lessons = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->setStudent($student);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    /**
     * True once the student was deleted and only the invoices are kept: read-only, no new lessons (F3).
     */
    public function isPersonDeleted(): bool
    {
        return null === $this->student;
    }

    /**
     * Unlinks the student before their personal data is deleted. Unlike setStudent(null), billToGuardian stays as it
     * was when the invoice was issued.
     */
    public function detachStudent(): void
    {
        $this->student?->getEnrollments()->removeElement($this);
        $this->student = null;
    }

    /**
     * Also sets the default for billToGuardian.
     */
    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        $startsOn = $this->course->getStartsOn();
        $this->billToGuardian = null !== $student && null !== $student->getGuardian()
            && null !== $startsOn && $student->isMinorOn($startsOn);

        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): static
    {
        $this->weekday = $weekday;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

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

    public function getPlannedLessonsReason(): ?string
    {
        return $this->plannedLessonsReason;
    }

    public function setPlannedLessonsReason(?string $plannedLessonsReason): static
    {
        $this->plannedLessonsReason = $plannedLessonsReason;

        return $this;
    }

    /**
     * True if the number was changed for this enrollment (or the course's number was changed afterwards).
     */
    public function hasOwnPlannedLessons(): bool
    {
        return $this->plannedLessons !== $this->course->getPlannedLessons();
    }

    public function getBilling(): Billing
    {
        return $this->billing;
    }

    public function setBilling(Billing $billing): static
    {
        $this->billing = $billing;

        return $this;
    }

    public function isBillToGuardian(): bool
    {
        return $this->billToGuardian;
    }

    public function setBillToGuardian(bool $billToGuardian): static
    {
        $this->billToGuardian = $billToGuardian;

        return $this;
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons->add($lesson);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): static
    {
        $this->lessons->removeElement($lesson);

        return $this;
    }

    /**
     * Lessons that count towards the planned number (all except cancellations by Sonja).
     */
    public function countedLessons(): int
    {
        return $this->lessons->filter(static fn (Lesson $lesson): bool => $lesson->counts())->count();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }
}
