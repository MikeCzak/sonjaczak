<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
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
     * Lessons of a course that are not cancelled and overlap the given time, except one lesson (usually the one the
     * time belongs to). Used when a cancellation is withdrawn (whatever was put into the freed slot has to go) and
     * before a double lesson is added (it must not run into another lesson); umsetzung-admin.md C4, question 8.
     * Overlap means "starts before the other ends and ends after it starts"; the end is start + duration, which DQL
     * cannot add up, so the candidates of that day are filtered here.
     *
     * @return list<Lesson>
     */
    public function findOverlapping(Course $course, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, ?Lesson $except = null): array
    {
        $query = $this->createQueryBuilder('l')
            ->addSelect('e', 's')
            ->join('l.enrollment', 'e')
            ->join('e.student', 's')
            ->where('e.course = :course')
            ->andWhere('l.cancelledBy IS NULL')
            ->andWhere('l.startsAt >= :dayStart AND l.startsAt < :endsAt')
            ->setParameter('course', $course)
            ->setParameter('dayStart', $startsAt->setTime(0, 0))
            ->setParameter('endsAt', $endsAt)
            ->orderBy('l.startsAt');
        if (null !== $except?->getId()) {
            $query->andWhere('l != :except')->setParameter('except', $except);
        }

        /** @var list<Lesson> $sameDay */
        $sameDay = $query->getQuery()->getResult();

        return array_values(array_filter($sameDay, static fn (Lesson $other): bool => $other !== $except && $other->getEndsAt() > $startsAt));
    }
}
