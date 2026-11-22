<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Release;
use App\Models\ReleaseRead;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Releases and "What's new" (A6.2, screen 70): a release has a version, a
 * date, plain-words notes per role and the flags it rolls out to everyone.
 * Each person sees the latest note for their role once, on the web and the
 * phone, until they dismiss it.
 */
final readonly class Releases
{
    /** Notes older than this don't show as new. */
    public const NEW_FOR_DAYS = 30;

    public function __construct(
        private Flags $flags,
        private Rollouts $rollouts,
        private AuditLogger $audit,
        private ActiveRole $roles,
    ) {}

    /**
     * @param  array{version: string, released_on: string, notes: array<string, string>, flags: list<string>}  $data
     */
    public function publish(array $data, User $actor): Release
    {
        return DB::transaction(function () use ($data, $actor): Release {
            $notes = array_filter($data['notes'], static fn (string $note): bool => trim($note) !== '');

            $release = Release::query()->create([
                'version' => $data['version'],
                'released_on' => $data['released_on'],
                'notes' => $notes,
                'flags' => $data['flags'] === [] ? null : $data['flags'],
                'published_at' => now(),
                'created_by' => $actor->id,
            ]);

            foreach ($data['flags'] as $key) {
                $this->rollouts->toEveryone($this->flags->flag($key), $actor, $release->version);
            }

            $this->audit->record('release.published', 'release', $release->version, [
                'from' => null,
                'to' => $release->version,
                'roles' => array_keys($notes),
                'flags' => $data['flags'],
            ], $actor);

            return $release;
        });
    }

    /** The latest release with a note for the person's role that they haven't dismissed. */
    public function whatsNew(User $user): ?Release
    {
        $role = $this->roles->for($user)?->role;

        if ($role === null) {
            return null;
        }

        return Release::query()
            ->whereNotNull('published_at')
            ->whereDate('released_on', '>=', CarbonImmutable::today()->subDays(self::NEW_FOR_DAYS)->toDateString())
            ->whereDate('released_on', '<=', CarbonImmutable::today()->toDateString())
            ->whereDoesntHave('reads', static fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('released_on')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->first(static fn (Release $release): bool => $release->noteFor($role->value) !== null);
    }

    /**
     * What's new for the person, as the web and the phone show it.
     *
     * @return array{id: int, version: string, released_on: string, note: string}|null
     */
    public function card(User $user): ?array
    {
        $release = $this->whatsNew($user);
        $role = $this->roles->for($user)?->role;
        $note = $role === null ? null : $release?->noteFor($role->value);

        return $release === null || $note === null ? null : [
            'id' => $release->id,
            'version' => $release->version,
            'released_on' => $release->released_on->toDateString(),
            'note' => $note,
        ];
    }

    public function dismiss(Release $release, User $user): void
    {
        ReleaseRead::query()->firstOrCreate(['release_id' => $release->id, 'user_id' => $user->id], ['dismissed_at' => now()]);
    }
}
