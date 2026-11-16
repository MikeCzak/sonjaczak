<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    /**
     * Planned lessons between two points in time, with enrollment and student, in time order.
     *
     * @return list<Lesson>
     */
    public function findPlannedBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        /** @var list<Lesson> */
        return $this->createQueryBuilder('l')
            ->addSelect('e', 's')
            ->join('l.enrollment', 'e')
            ->join('e.student', 's')
            // Not cancelled. IS NULL, not "= NULL": a comparison with NULL is never true in SQL
            ->where('l.cancelledBy IS NULL')
            ->andWhere('l.startsAt >= :from AND l.startsAt < :until')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('l.startsAt')
            ->getQuery()
            ->getResult();
    }

    /**
     * Lessons of a course that are not cancelled and overlap the given one in time (not the lesson itself). Used
     * when a cancellation is withdrawn: whatever was put into the freed slot has to go (umsetzung-admin.md C4,
     * question 8, points 4 and 5). Overlap means "starts before the other ends and ends after it starts"; the end
     * is start + duration, which DQL cannot add up, so the candidates of that day are filtered here.
     *
     * @return list<Lesson>
     */
    public function findOverlapping(Lesson $lesson): array
    {
        $startsAt = $lesson->getStartsAt();
        $endsAt = $lesson->getEndsAt();
        if (null === $startsAt || null === $endsAt) {
            return [];
        }

        /** @var list<Lesson> $sameDay */
        $sameDay = $this->createQueryBuilder('l')
            ->addSelect('e', 's')
            ->join('l.enrollment', 'e')
            ->join('e.student', 's')
            ->where('e.course = :course')
            ->andWhere('l.cancelledBy IS NULL')
            ->andWhere('l != :lesson')
            ->andWhere('l.startsAt >= :dayStart AND l.startsAt < :endsAt')
            ->setParameter('course', $lesson->getEnrollment()->getCourse())
            ->setParameter('lesson', $lesson)
            ->setParameter('dayStart', $startsAt->setTime(0, 0))
            ->setParameter('endsAt', $endsAt)
            ->orderBy('l.startsAt')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($sameDay, static fn (Lesson $other): bool => $other->getEndsAt() > $startsAt));
    }
}
