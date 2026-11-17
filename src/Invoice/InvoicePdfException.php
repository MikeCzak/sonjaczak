<?php

declare(strict_types=1);

namespace App\Invoice;

/**
 * The PDF of an invoice could not be rendered. Thrown inside the transaction of InvoiceIssuer, so nothing is saved
 * and no number is used up (umsetzung-admin.md D2b, Option A).
 */
final class InvoicePdfException extends \RuntimeException
{
}
