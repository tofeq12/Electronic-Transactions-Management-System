<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * TRX-YYYY-NNNN transaction numbering (format fixed by the existing UI).
 * Generation is collision-tolerant: callers retry on a unique-key violation.
 */
class TransactionNumber
{
    public static function next(): string
    {
        $prefix = 'TRX-'.date('Y').'-';

        $last = Transaction::query()
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
