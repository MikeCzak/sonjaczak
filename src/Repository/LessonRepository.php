<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\LessonStatus;
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
            ->where('l.status = :planned')
            ->andWhere('l.startsAt >= :from AND l.startsAt < :until')
            ->setParameter('planned', LessonStatus::Planned)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('l.startsAt')
            ->getQuery()
            ->getResult();
    }
}
