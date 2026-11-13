<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CourseBreak;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourseBreak>
 */
class CourseBreakRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseBreak::class);
    }
}
