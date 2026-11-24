<?php

declare(strict_types=1);

namespace App\ContractReview;

/**
 * What a review read from a contract (A12.2, ADR 0048): the form, the key
 * terms (each with the page it's on), and anything the reader itself
 * flagged beyond REOS's own checks.
 *
 * @phpstan-type Term array{code: string, value: string, page: int|null}
 * @phpstan-type ReaderFinding array{severity: 'high'|'check', title: string, detail: string, page: int|null}
 */
final readonly class ContractReading
{
    /**
     * @param  string|null  $form  the TREC form number ("20-18"), or null when it isn't one REOS knows
     * @param  array<string, Term>  $terms  by code
     * @param  list<string>  $addenda  addenda found (codes from TrecForms::ADDENDA)
     * @param  list<ReaderFinding>  $findings
     */
    public function __construct(
        public ?string $form,
        public array $terms,
        public array $addenda = [],
        public array $findings = [],
    ) {}

    public function value(string $code): ?string
    {
        return $this->terms[$code]['value'] ?? null;
    }

    public function page(string $code): ?int
    {
        return $this->terms[$code]['page'] ?? null;
    }
}
