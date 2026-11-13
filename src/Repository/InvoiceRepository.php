<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Next number of the year: highest number + 1, starting at 1. Only safe inside the lock taken by
     * App\Invoice\InvoiceIssuer, otherwise two requests can get the same number.
     */
    public function nextNumber(int $year): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.number)')
            ->where('i.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    /**
     * Invoices of a year with enrollment, course and student, newest number first.
     *
     * @return list<Invoice>
     */
    public function findByYear(int $year): array
    {
        /** @var list<Invoice> */
        return $this->createQueryBuilder('i')
            ->addSelect('e', 'c', 's')
            ->join('i.enrollment', 'e')
            ->join('e.course', 'c')
            ->join('e.student', 's')
            ->where('i.year = :year')
            ->setParameter('year', $year)
            ->orderBy('i.number', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Years that have invoices, newest first.
     *
     * @return list<int>
     */
    public function years(): array
    {
        /** @var list<int|string> $years */
        $years = $this->createQueryBuilder('i')
            ->select('DISTINCT i.year')
            ->orderBy('i.year', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(intval(...), $years);
    }

    /**
     * All invoices that are not credit notes, with payments and credit notes, for the reminder check. Filtering by
     * "open" happens in PHP (Invoice::getMissedDeadline()), the status is not stored.
     *
     * @return list<Invoice>
     */
    public function findOriginalsWithPayments(): array
    {
        /** @var list<Invoice> */
        return $this->createQueryBuilder('i')
            ->addSelect('p', 'n', 'np')
            ->leftJoin('i.payments', 'p')
            ->leftJoin('i.creditNotes', 'n')
            ->leftJoin('n.payments', 'np')
            ->where('i.credits IS NULL')
            ->orderBy('i.year')
            ->addOrderBy('i.number')
            ->getQuery()
            ->getResult();
    }
}
