<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * The platform audit log (A4.3b, screen 71): every event, filtered by
 * area, tenant, person and dates, for the screen and its CSV export.
 */
final readonly class AuditLog
{
    /** Areas, by action prefix. */
    public const GROUPS = [
        'sign_in' => ['auth.', 'account.'],
        'tenants' => ['tenant.', 'support.', 'seats.'],
        'people' => ['role.', 'invite.'],
        'brand' => ['brand.', 'domain.', 'sso.'],
        'integrations' => ['integration.', 'catalogue.', 'connection.'],
        'states' => ['state.'],
        'content' => ['course.', 'lesson.'],
        'privacy' => ['privacy.'],
    ];

    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  array{q: string|null, group: string|null, tenant: int|null, from: string|null, to: string|null}  $filters
     * @return CursorPaginator<int, AuditEvent>
     */
    public function page(array $filters, int $perPage = 50): CursorPaginator
    {
        return $this->tenancy->asPlatform(fn (): CursorPaginator => $this->query($filters)->cursorPaginate($perPage)->withQueryString());
    }

    /**
     * @param  array{q: string|null, group: string|null, tenant: int|null, from: string|null, to: string|null}  $filters
     * @return LazyCollection<int, AuditEvent>
     */
    public function all(array $filters): LazyCollection
    {
        return $this->tenancy->asPlatform(fn (): LazyCollection => $this->query($filters)->limit(50_000)->lazyByIdDesc(1000)->remember());
    }

    /**
     * @param  array{q: string|null, group: string|null, tenant: int|null, from: string|null, to: string|null}  $filters
     * @return Builder<AuditEvent>
     */
    private function query(array $filters): Builder
    {
        $prefixes = self::GROUPS[$filters['group'] ?? ''] ?? null;
        $search = trim((string) $filters['q']);

        return AuditEvent::query()->with(['actor', 'organisation'])
            ->when($prefixes !== null, static fn (Builder $q) => $q->where(static function (Builder $any) use ($prefixes): void {
                foreach ((array) $prefixes as $prefix) {
                    $any->orWhere('action', 'like', $prefix.'%');
                }
            }))
            ->when($filters['tenant'] !== null, static fn (Builder $q) => $q->where('organisation_id', $filters['tenant']))
            ->when($filters['from'] !== null, static fn (Builder $q) => $q->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when($filters['to'] !== null, static fn (Builder $q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->when($search !== '', static fn (Builder $q) => $q->where(static function (Builder $match) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $match->whereRaw('lower(action) like ?', [$like])
                    ->orWhereRaw('lower(ip_address) like ?', [$like])
                    ->orWhereHas('actor', static fn (Builder $actor) => $actor->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]));
            }))
            ->orderByDesc('id');
    }

    /**
     * What a row shows as "before" and "after" (`before`/`after`, or the older `from`/`to`).
     *
     * @return array{0: mixed, 1: mixed}
     */
    public static function change(AuditEvent $event): array
    {
        $p = $event->properties;

        return [$p['before'] ?? $p['from'] ?? null, $p['after'] ?? $p['to'] ?? null];
    }
}
