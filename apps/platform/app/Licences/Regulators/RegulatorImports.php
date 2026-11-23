<?php

declare(strict_types=1);

namespace App\Licences\Regulators;

use App\Integrations\Catalogue;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Models\RegulatorImport;
use App\Models\RegulatorRecord;
use App\Services\Integrations\IntegrationHub;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The nightly import of regulators' public licence data (B10.3, decision 8):
 * every register entry with a `regulator` block (TREC's license holders and
 * inspectors, TDI's licensees). Live: the files from the Texas Open Data
 * Portal. Simulator (outside production): demo files in TREC's and the
 * portal's own formats, read the same way.
 *
 * Each import replaces the authority's records. A file far smaller than the
 * last one (a truncated download) keeps yesterday's records instead.
 */
final readonly class RegulatorImports
{
    /** A new import must have at least this share of the last one's rows. */
    public const MIN_SHARE = 0.5;

    private const CHUNK = 1000;

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private Application $app,
        private Factory $http,
    ) {}

    /** @return list<IntegrationDefinition> */
    public function all(): array
    {
        return array_values(array_filter($this->catalogue->all(), static fn (IntegrationDefinition $d): bool => $d->regulator !== null));
    }

    /** Imports one regulator's files; null when it's off. */
    public function run(IntegrationDefinition $definition): ?RegulatorImport
    {
        $regulator = $definition->regulator;
        $mode = $this->hub->status($definition)->effectiveMode();

        if ($regulator === null || $mode === IntegrationMode::Off || ($mode === IntegrationMode::Simulator && $this->app->isProduction())) {
            return null;
        }

        $simulator = $mode === IntegrationMode::Simulator;
        $started = CarbonImmutable::now();
        $import = RegulatorImport::query()->create(['integration' => $definition->id, 'source' => $simulator ? 'simulator' : 'live', 'started_at' => $started]);
        $before = RegulatorRecord::query()->where('authority', $regulator->authority)->count();
        $rows = 0;
        $temporary = [];

        try {
            foreach ($regulator->files as $file) {
                $path = $simulator ? $this->demoFile($definition->id, $file['kind']) : $temporary[] = $this->download($file['url']);
                $rows += $this->load($path, $regulator->authority, $file['kind'], $started);
            }

            if ($before > 0 && $rows < $before * self::MIN_SHARE) {
                throw new RuntimeException("Only {$rows} rows against {$before} last time; keeping the last import.");
            }

            $removed = RegulatorRecord::query()->where('authority', $regulator->authority)->where('imported_at', '<', $started)->delete();
            $import->forceFill(['rows' => $rows, 'removed' => $removed, 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            $import->forceFill(['rows' => $rows, 'error' => Str::limit($e->getMessage(), 480), 'finished_at' => now()])->save();
        } finally {
            foreach ($temporary as $path) {
                @unlink($path);
            }
        }

        return $import;
    }

    /** @param  'agents'|'inspectors'|'insurance'  $kind */
    private function load(string $path, string $authority, string $kind, CarbonImmutable $started): int
    {
        $count = 0;
        $chunk = [];
        $default = match ($kind) {
            'agents' => 'SALE',
            'inspectors' => 'PRIN',
            'insurance' => 'INSURANCE',
        };

        foreach (RegulatorFile::rows($path, $authority === 'TDI' ? 'TDI' : 'TREC', $default) as $row) {
            // One row per licence: the file's last word on it wins.
            $chunk[$row['licence_type'].'|'.$row['number']] = [...$row, 'authority' => $authority, 'kind' => $kind, 'imported_at' => $started];

            if (count($chunk) >= self::CHUNK) {
                $count += $this->save($chunk);
                $chunk = [];
            }
        }

        return $count + $this->save($chunk);
    }

    /** @param  array<string, array<string, mixed>>  $rows */
    private function save(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $values = array_values($rows);
        DB::transaction(static fn () => RegulatorRecord::query()->upsert(
            $values,
            ['authority', 'licence_type', 'number'],
            array_values(array_diff(array_keys($values[0]), ['authority', 'licence_type', 'number'])),
        ));

        return count($values);
    }

    private function download(string $url): string
    {
        $path = tempnam(sys_get_temp_dir(), 'regulator-');

        if ($path === false) {
            throw new RuntimeException('No room for the download.');
        }

        $response = $this->http->connectTimeout(15)->timeout(900)->withUserAgent('REOS-licence-lookup/1.0')->sink($path)->get($url);

        if (! $response->successful()) {
            @unlink($path);

            throw new RuntimeException("The regulator's file answered {$response->status()}.");
        }

        return $path;
    }

    private function demoFile(string $integration, string $kind): string
    {
        foreach (['tsv', 'csv'] as $extension) {
            $path = resource_path("regulators/{$integration}-{$kind}.{$extension}");

            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("No demo file for {$integration} {$kind}.");
    }
}
