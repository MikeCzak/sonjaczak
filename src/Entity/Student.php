<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Address;
use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A student of Sonja (docs/planung/datenmodell.md 3.2). Personal data under the GDPR: only visible in the admin area.
 */
#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ORM\Index(name: 'idx_student_name', fields: ['lastName', 'firstName'])]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    /**
     * Birth date instead of age: an age is wrong a year later. Optional, because adults often do not give it.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual('today')]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $phone = null;

    #[ORM\Embedded(class: Address::class)]
    #[Assert\Valid]
    private Address $address;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Owning side (holds student.guardian_id): only changes made here are saved. No cascade: a guardian is created
     * and deleted on its own, and the database refuses to delete one that still has students.
     */
    #[ORM\ManyToOne(inversedBy: 'students')]
    private ?Guardian $guardian = null;

    /**
     * Login of the student, later (ROLE_STUDENT). Owning side, because the relation starts from the student:
     * the student exists first, an account may follow (datenmodell.md 3.1). Unique: one account per student.
     */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(unique: true)]
    private ?User $user = null;

    /**
     * Inverse side. Deleted with the student: a person who only came for a trial lesson is deleted completely.
     *
     * @var Collection<int, TrialLesson>
     */
    #[ORM\OneToMany(targetEntity: TrialLesson::class, mappedBy: 'student', cascade: ['remove'])]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $trialLessons;

    /**
     * Inverse side, only for reading (StudentVoter: no delete while enrolled).
     *
     * @var Collection<int, Enrollment>
     */
    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'student')]
    private Collection $enrollments;

    public function __construct()
    {
        $this->address = new Address();
        $this->trialLessons = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    /**
     * Under 18 on the given day; unknown birth date counts as adult.
     */
    public function isMinorOn(\DateTimeImmutable $day): bool
    {
        return null !== $this->birthDate && $this->birthDate->modify('+18 years') > $day;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = null === $email ? null : mb_strtolower(trim($email));

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getGuardian(): ?Guardian
    {
        return $this->guardian;
    }

    public function setGuardian(?Guardian $guardian): static
    {
        $this->guardian = $guardian;

        return $this;
    }

    /**
     * @return Collection<int, TrialLesson>
     */
    public function getTrialLessons(): Collection
    {
        return $this->trialLessons;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
