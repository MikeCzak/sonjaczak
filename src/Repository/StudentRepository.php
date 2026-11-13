<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    /**
     * Students for the list, sorted by name. The search looks at first name, last name and guardian name,
     * ignoring upper/lower case (DQL has no ILIKE, so both sides are lowercased).
     *
     * @return list<Student>
     */
    public function search(?string $term): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.guardian', 'g')
            ->addSelect('g')
            ->orderBy('s.lastName')
            ->addOrderBy('s.firstName');

        $term = trim((string) $term);
        if ('' !== $term) {
            // % and _ are wildcards in LIKE; escaped with backslash (PostgreSQL's default escape character)
            $pattern = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $qb->andWhere('LOWER(s.firstName) LIKE :term OR LOWER(s.lastName) LIKE :term OR LOWER(g.name) LIKE :term')
                ->setParameter('term', $pattern);
        }

        /** @var list<Student> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Students who are not enrolled in the course yet, for choosing a new enrollment.
     *
     * @return list<Student>
     */
    public function notEnrolledIn(Course $course): array
    {
        $enrolled = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(e.student)')
            ->from(Enrollment::class, 'e')
            ->where('e.course = :course');

        /** @var list<Student> */
        return $this->createQueryBuilder('s')
            ->where('s.id NOT IN ('.$enrolled->getDQL().')')
            ->setParameter('course', $course)
            ->orderBy('s.lastName')
            ->addOrderBy('s.firstName')
            ->getQuery()
            ->getResult();
    }
}
