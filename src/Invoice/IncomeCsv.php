<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Entity\Payment;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Income of a year as CSV for Sonja's Excel (datenmodell.md 9): semicolon as separator, decimal comma, UTF-8 with
 * byte order mark (BOM), so that Excel with German settings shows umlauts and amounts correctly.
 *
 * Text cells that start with = + - @ would be run as formulas by Excel ("CSV injection"); they get a leading
 * apostrophe, which Excel hides.
 */
final readonly class IncomeCsv
{
    private const string BOM = "\u{FEFF}";

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param list<Payment> $payments
     */
    public function build(array $payments): string
    {
        $stream = fopen('php://temp', 'r+') ?: throw new \RuntimeException('Cannot open temporary stream.');
        $this->row($stream, array_map(
            fn (string $column): string => $this->translator->trans('income.csv.'.$column, [], 'admin'),
            ['date', 'invoice', 'recipient', 'student', 'description', 'method', 'amount'],
        ));

        foreach ($payments as $payment) {
            $invoice = $payment->getInvoice();
            $this->row($stream, [
                $payment->getPaidOn()->format('d.m.Y'),
                $invoice->getDisplayNumber(),
                $this->text($invoice->getRecipient()->getName()),
                $this->text($invoice->getEnrollment()->getStudent()?->getFullName() ?? ''),
                $this->text($invoice->getDescription()),
                $this->translator->trans($payment->getMethod()->labelKey(), [], 'admin'),
                number_format($payment->getAmountCents() / 100, 2, ',', ''),
            ]);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return self::BOM.$csv;
    }

    /**
     * @param resource     $stream
     * @param list<string> $cells
     */
    private function row($stream, array $cells): void
    {
        // escape: '' turns off PHP's own backslash escaping, which Excel does not know (deprecated default in PHP 8.4)
        fputcsv($stream, $cells, ';', '"', '', "\r\n");
    }

    private function text(string $value): string
    {
        return '' !== $value && str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }
}
