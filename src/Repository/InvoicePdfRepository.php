<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Invoice;
use App\Entity\InvoicePdf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoicePdf>
 */
class InvoicePdfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoicePdf::class);
    }

    public function findForInvoice(Invoice $invoice): ?InvoicePdf
    {
        return $this->findOneBy(['invoice' => $invoice]);
    }

    /**
     * All PDFs of a course (invoices and credit notes), with invoice, enrollment and student for the file names, in
     * number order.
     *
     * @return list<InvoicePdf>
     */
    public function findForCourse(Course $course): array
    {
        /** @var list<InvoicePdf> */
        return $this->createQueryBuilder('p')
            ->addSelect('i', 'e', 's')
            ->join('p.invoice', 'i')
            ->join('i.enrollment', 'e')
            ->join('e.student', 's')
            ->where('e.course = :course')
            ->setParameter('course', $course)
            ->orderBy('i.year')
            ->addOrderBy('i.number')
            ->getQuery()
            ->getResult();
    }
}
