<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Billing\GatewaySettings;
use App\Billing\OrderStatus;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\ReconciliationRun;
use App\Models\Refund;
use App\Models\User;
use App\Services\Integrations\IntegrationHub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * The platform overview (screen 58, A6.1): tenants and people, who is
 * online, latency (p95) and uptime against the targets, traffic, the cell,
 * integrations, today's payments and the queues, each linking to its area.
 * Built from REOS's own tables and the per-minute request metrics.
 */
final readonly class PlatformOverview
{
    /** Targets from the web journey (screen 58). */
    public const P95_TARGET_MS = 300;

    public const UPTIME_TARGET = 99.95;

    /** Queues the workers listen on. */
    public const QUEUES = ['default', 'billing', 'content', 'connections'];

    public function __construct(private IntegrationHub $hub, private GatewaySettings $gateways) {}

    /** @return array<string, mixed> */
    public function summary(string $range): array
    {
        $now = CarbonImmutable::now();
        $from = $range === '30d' ? $now->subDays(30) : $now->subDay();

        return [
            'range' => $range,
            'tenants' => $this->tenants(),
            'people' => $this->people(),
            'online' => User::query()->where('last_active_at', '>=', $now->subMinutes(5))->count(),
            'active_today' => User::query()->where('last_active_at', '>=', $now->startOfDay())->count(),
            'p95' => RequestMetrics::p95($this->histogram($from)),
            'p95_target' => self::P95_TARGET_MS,
            'uptime' => HealthChecks::uptime(30),
            'uptime_target' => self::UPTIME_TARGET,
            'health' => HealthChecks::latest(),
            'traffic' => $this->traffic($range, $now),
            'cells' => [[
                'cell' => 'tx-1',
                'region' => config()->string('reos.aws_region', 'us-east-2'),
                'tenants' => Organisation::query()->where('type', '!=', OrganisationType::Platform)->count(),
                'status' => (HealthChecks::latest()['ok'] ?? true) ? 'healthy' : 'unhealthy',
            ]],
            'integrations' => $this->integrations(),
            'payments' => $this->payments($now),
            'queues' => $this->queues(),
        ];
    }

    /** @return array{total: int, by_type: array<string, int>} */
    private function tenants(): array
    {
        $byType = [];

        foreach (Organisation::query()->where('type', '!=', OrganisationType::Platform)->selectRaw('type, COUNT(*) as total')->groupBy('type')->get() as $row) {
            $type = $row->getAttribute('type');
            $total = $row->getAttribute('total');
            $byType[$type instanceof OrganisationType ? $type->value : (is_string($type) ? $type : '')] = is_numeric($total) ? (int) $total : 0;
        }

        return ['total' => array_sum($byType), 'by_type' => $byType];
    }

    /** @return array{total: int, agents: int, candidates: int} */
    private function people(): array
    {
        $role = static fn (string $name): int => DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $name)->distinct()->count('model_has_roles.model_id');

        return ['total' => User::query()->count(), 'agents' => $role('agent'), 'candidates' => $role('candidate')];
    }

    /** @return array<string, int> */
    private function histogram(CarbonImmutable $from): array
    {
        $columns = ['requests', 'errors', 'over_2000', ...array_map(static fn (int $b): string => "under_{$b}", RequestMetrics::BUCKETS)];
        $row = DB::table('request_metrics')->where('minute', '>=', $from)
            ->selectRaw(implode(', ', array_map(static fn (string $c): string => "SUM({$c}) as {$c}", $columns)))->first();
        $histogram = [];

        foreach ($columns as $column) {
            $value = is_object($row) ? ($row->{$column} ?? 0) : 0;
            $histogram[$column] = is_numeric($value) ? (int) $value : 0;
        }

        return $histogram;
    }

    /**
     * Requests per hour for 24 hours, or per day for 30 days.
     *
     * @return list<array{at: string, requests: int, errors: int}>
     */
    private function traffic(string $range, CarbonImmutable $now): array
    {
        $daily = $range === '30d';
        $start = $daily ? $now->subDays(29)->startOfDay() : $now->subHours(23)->startOfHour();
        $buckets = [];

        for ($i = 0; $i < ($daily ? 30 : 24); $i++) {
            $at = $daily ? $start->addDays($i) : $start->addHours($i);
            $buckets[$at->format($daily ? 'Y-m-d' : 'Y-m-d H')] = ['at' => $at->toIso8601String(), 'requests' => 0, 'errors' => 0];
        }

        foreach (DB::table('request_metrics')->where('minute', '>=', $start)->get(['minute', 'requests', 'errors']) as $row) {
            $key = CarbonImmutable::parse(is_string($row->minute) ? $row->minute : 'now')->format($daily ? 'Y-m-d' : 'Y-m-d H');

            if (isset($buckets[$key])) {
                $buckets[$key]['requests'] += is_numeric($row->requests) ? (int) $row->requests : 0;
                $buckets[$key]['errors'] += is_numeric($row->errors) ? (int) $row->errors : 0;
            }
        }

        return array_values($buckets);
    }

    /** @return array{healthy: int, paused: list<string>, coming_soon: int, interest: int} */
    private function integrations(): array
    {
        $healthy = 0;
        $paused = [];
        $comingSoon = 0;

        foreach ($this->hub->overview() as ['definition' => $definition, 'status' => $status]) {
            if ($definition->launch === 'coming_soon') {
                $comingSoon++;
            } elseif ($status->pausedAt !== null) {
                $paused[] = $definition->name;
            } elseif (in_array($status->mode, [IntegrationMode::Live, IntegrationMode::Sandbox, IntegrationMode::Simulator], true)) {
                $healthy++;
            }
        }

        return ['healthy' => $healthy, 'paused' => $paused, 'coming_soon' => $comingSoon, 'interest' => DB::table('integration_interests')->count()];
    }

    /** @return array<string, mixed> */
    private function payments(CarbonImmutable $now): array
    {
        $today = Order::query()->where('created_at', '>=', $now->startOfDay());
        $paid = (clone $today)->where('status', OrderStatus::Paid);
        $started = (clone $today)->count();
        $default = collect($this->gateways->overview())->firstWhere('is_default', true);
        $run = ReconciliationRun::query()->latest('day')->latest('id')->first();

        return [
            'gateway' => is_array($default) ? ['name' => $default['name'], 'health' => $default['health'], 'paused' => $default['paused']] : null,
            'charges' => (clone $paid)->count(),
            'amount' => (int) (clone $paid)->sum('total_cents'),
            'unfinished_percent' => $started === 0 ? 0.0 : round((clone $today)->where('status', OrderStatus::Expired)->count() * 100 / $started, 1),
            'refunds' => Refund::query()->where('created_at', '>=', $now->startOfDay())->where('status', 'done')->count(),
            'reconciliation' => $run === null ? null : ['day' => $run->day->toDateString(), 'status' => $run->status],
        ];
    }

    /** @return array{waiting: list<array{queue: string, size: int|null}>, failed: int} */
    private function queues(): array
    {
        $waiting = [];

        foreach (self::QUEUES as $queue) {
            try {
                $size = Queue::size($queue);
            } catch (Throwable) {
                $size = null;
            }

            $waiting[] = ['queue' => $queue, 'size' => $size];
        }

        return ['waiting' => $waiting, 'failed' => DB::table('failed_jobs')->count()];
    }
}
