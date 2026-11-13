<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Payments received in a calendar year (paidOn), with invoice, enrollment and student, in date order. Basis of
     * the income export: income counts in the year it is received (§ 19 Abs. 1 EStG).
     *
     * @return list<Payment>
     */
    public function findByYear(int $year): array
    {
        /** @var list<Payment> */
        return $this->createQueryBuilder('p')
            ->addSelect('i', 'e', 's')
            ->join('p.invoice', 'i')
            ->join('i.enrollment', 'e')
            ->join('e.student', 's')
            ->where('p.paidOn >= :from AND p.paidOn < :to')
            ->setParameter('from', new \DateTimeImmutable(\sprintf('%d-01-01', $year)))
            ->setParameter('to', new \DateTimeImmutable(\sprintf('%d-01-01', $year + 1)))
            ->orderBy('p.paidOn')
            ->addOrderBy('p.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Years with payments, newest first.
     *
     * @return list<int>
     */
    public function years(): array
    {
        // A scalar result is not converted to objects: the date arrives as the database string "2026-12-30"
        /** @var list<string> $dates */
        $dates = $this->createQueryBuilder('p')
            ->select('DISTINCT p.paidOn')
            ->getQuery()
            ->getSingleColumnResult();

        $years = array_values(array_unique(array_map(static fn (string $date): int => (int) substr($date, 0, 4), $dates)));
        rsort($years);

        return $years;
    }
}
