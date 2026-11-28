<?php

declare(strict_types=1);

namespace App\Accounting;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A balanced journal entry (B17.3): debits equal credits, in cents.
 *
 * @phpstan-type Line array{account: string, posting: 'Debit'|'Credit', cents: int, description: string}
 */
final readonly class JournalEntry
{
    /** @param list<Line> $lines */
    public function __construct(public CarbonImmutable $date, public string $docNumber, public string $memo, public array $lines)
    {
        $debits = array_sum(array_map(static fn (array $l): int => $l['posting'] === 'Debit' ? $l['cents'] : 0, $lines));
        $credits = array_sum(array_map(static fn (array $l): int => $l['posting'] === 'Credit' ? $l['cents'] : 0, $lines));

        if ($debits !== $credits || $debits === 0) {
            throw new InvalidArgumentException('A journal entry must balance.');
        }
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (array $l): int => $l['posting'] === 'Debit' ? $l['cents'] : 0, $this->lines));
    }

    /**
     * The body QuickBooks Online's JournalEntry endpoint takes.
     *
     * @return array{TxnDate: string, DocNumber: string, PrivateNote: string, Line: list<array<string, mixed>>}
     */
    public function toQuickBooks(): array
    {
        $lines = [];

        foreach ($this->lines as $l) {
            if ($l['cents'] === 0) {
                continue;
            }

            $lines[] = [
                'DetailType' => 'JournalEntryLineDetail', 'Amount' => round($l['cents'] / 100, 2), 'Description' => $l['description'],
                'JournalEntryLineDetail' => ['PostingType' => $l['posting'], 'AccountRef' => ['value' => $l['account']]],
            ];
        }

        return ['TxnDate' => $this->date->toDateString(), 'DocNumber' => mb_substr($this->docNumber, 0, 21), 'PrivateNote' => mb_substr($this->memo, 0, 4000), 'Line' => $lines];
    }
}
