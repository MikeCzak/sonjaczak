<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * Courses running on a day (started, not yet ended), with enrollments, students and lessons in one query.
     *
     * @return list<Course>
     */
    public function findRunningOn(\DateTimeImmutable $day): array
    {
        /** @var list<Course> */
        return $this->createQueryBuilder('c')
            ->addSelect('e', 's', 'l')
            ->leftJoin('c.enrollments', 'e')
            ->leftJoin('e.student', 's')
            ->leftJoin('e.lessons', 'l')
            ->where('c.startsOn <= :day')
            ->andWhere('c.endsOn IS NULL OR c.endsOn >= :day')
            ->setParameter('day', $day)
            ->orderBy('c.startsOn')
            ->addOrderBy('e.weekday')
            ->addOrderBy('e.startTime')
            ->addOrderBy('l.startsAt')
            ->getQuery()
            ->getResult();
    }
}
