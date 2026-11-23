<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Regulators\RegulatorImports;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Nightly (B10.3): TREC's and TDI's public licence data, into the lookup table. */
#[Signature('reos:regulators:import {--only= : Only this register id}')]
#[Description("Import regulators' public licence data (TREC, TDI) for instant licence checks")]
final class RegulatorImportCommand extends Command
{
    public function handle(RegulatorImports $imports): int
    {
        $only = $this->option('only');
        $failed = false;

        foreach ($imports->all() as $definition) {
            if (is_string($only) && $only !== $definition->id) {
                continue;
            }

            $import = $imports->run($definition);

            if ($import === null) {
                $this->components->info("{$definition->id}: off.");
            } elseif ($import->error !== null) {
                $failed = true;
                $this->components->error("{$definition->id}: {$import->error}");
            } else {
                $this->components->info("{$definition->id}: {$import->rows} records ({$import->source}), {$import->removed} removed.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
