<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrialLesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrialLesson>
 */
class TrialLessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrialLesson::class);
    }

    /**
     * Newest first, with the student loaded in the same query.
     *
     * @return list<TrialLesson>
     */
    public function findAllWithStudent(): array
    {
        /** @var list<TrialLesson> */
        return $this->createQueryBuilder('t')
            ->join('t.student', 's')
            ->addSelect('s')
            ->orderBy('t.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trial lessons between two points in time, with student, in time order.
     *
     * @return list<TrialLesson>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        /** @var list<TrialLesson> */
        return $this->createQueryBuilder('t')
            ->addSelect('s')
            ->join('t.student', 's')
            ->where('t.startsAt >= :from AND t.startsAt < :until')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->orderBy('t.startsAt')
            ->getQuery()
            ->getResult();
    }
}
