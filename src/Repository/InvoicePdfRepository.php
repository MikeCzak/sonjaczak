<?php

declare(strict_types=1);

namespace App\Repository;

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
}
