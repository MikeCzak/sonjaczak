<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    /**
     * The enrollments of a course with students and all lessons in one query, for the course grid. The lessons are
     * fetch-joined unfiltered, so the collections are complete (a WHERE on l would leave them half-loaded).
     *
     * @return list<Enrollment>
     */
    public function findForGrid(Course $course): array
    {
        /** @var list<Enrollment> */
        return $this->createQueryBuilder('e')
            ->addSelect('s', 'l')
            // Inner join on purpose: an enrollment without a person (F3) has no lessons, nothing to show in the grid
            ->join('e.student', 's')
            ->leftJoin('e.lessons', 'l')
            ->where('e.course = :course')
            ->setParameter('course', $course)
            ->orderBy('e.weekday')
            ->addOrderBy('e.startTime')
            ->addOrderBy('l.startsAt')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enrollments that have invoices or credit notes, with all of them (inner join: every invoice has an enrollment,
     * so the collections are complete), for App\Privacy\PersonalDataPurger.
     *
     * @return list<Enrollment>
     */
    public function findWithInvoices(): array
    {
        /** @var list<Enrollment> */
        return $this->createQueryBuilder('e')
            ->join('e.invoices', 'i')
            ->addSelect('i')
            ->getQuery()
            ->getResult();
    }
}
