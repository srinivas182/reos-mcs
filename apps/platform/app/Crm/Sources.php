<?php

declare(strict_types=1);

namespace App\Crm;

use App\Integrations\Catalogue;
use App\Models\ContactImport;
use Carbon\CarbonImmutable;

/** A contact source's name as people know it: "Phone contacts", "Follow Up Boss", "CSV import, Jan 4, 2027" (B7.1, B8.3b). */
final readonly class Sources
{
    public function __construct(private Catalogue $catalogue) {}

    public function name(string $source): string
    {
        return match (true) {
            $source === Contacts::PHONE => __('crm.sources.phone'),
            $source === Contacts::REOS => 'REOS',
            str_starts_with($source, 'csv-') => $this->csv((int) substr($source, 4)),
            default => $this->catalogue->find($source)->name ?? $source,
        };
    }

    private function csv(int $id): string
    {
        $at = ContactImport::query()->find($id)?->created_at;
        $date = $at === null ? CarbonImmutable::now() : CarbonImmutable::instance($at);

        $local = $date->locale(app()->getLocale());

        return __('crm.sources.csv', ['date' => $local instanceof CarbonImmutable ? $local->isoFormat('ll') : $date->toDateString()]);
    }
}
