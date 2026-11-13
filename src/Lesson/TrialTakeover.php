<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Entity\TrialLesson;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saves a new enrollment and takes the student's trial lessons over as held lessons (datenmodell.md 3.6):
 * each trial lesson becomes a Lesson with fromTrial = true and is deleted, then the remaining lessons are generated
 * (the taken-over ones count, so 14 instead of 15).
 */
final readonly class TrialTakeover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LessonPlanner $planner,
    ) {
    }

    /**
     * Everything in one transaction: either the enrollment with all its lessons exists and the trial lessons are
     * gone, or nothing changed. (A single flush is atomic anyway; wrapInTransaction() makes it explicit and stays
     * correct if a step with its own flush is added later.)
     *
     * @param iterable<TrialLesson> $trials
     */
    public function enroll(Enrollment $enrollment, iterable $trials): void
    {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($enrollment, $trials): void {
            $entityManager->persist($enrollment);

            foreach ($trials as $trial) {
                $student = $trial->getStudent();
                if (null === $student || $student !== $enrollment->getStudent() || null === $trial->getStartsAt()) {
                    throw new \LogicException('Only trial lessons of the enrolled student can be taken over.');
                }

                new Lesson($enrollment, $trial->getStartsAt(), $trial->getDurationMinutes())
                    ->setStatus(LessonStatus::Held)
                    ->setNote($trial->getNote())
                    ->setFromTrial(true);
                $student->getTrialLessons()->removeElement($trial);
                $entityManager->remove($trial);
            }

            $this->planner->fillUp($enrollment);
        });
    }
}
