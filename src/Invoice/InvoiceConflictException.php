<?php

declare(strict_types=1);

namespace App\Invoice;

/**
 * Another request created an invoice or credit note for the same enrollment or invoice in the meantime (double
 * click, second tab). Thrown inside the numbering transaction, so nothing is saved.
 */
final class InvoiceConflictException extends \RuntimeException
{
}
