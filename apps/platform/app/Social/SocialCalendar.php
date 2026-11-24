<?php

declare(strict_types=1);

namespace App\Social;

use App\Models\AgentProfile;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The content calendar (B12.3, ADR 0052; web screen 17, phone My posts).
 *
 * A week (Sunday to Saturday) or a month of the agent's posts, in their
 * time zone: scheduled posts at their time, posts that went out on the
 * day they went, and drafts planned for a day. A scheduled post moves to
 * another day at the same time; a draft moves to another day; anything
 * can be reused as a new draft on a chosen day. Posts are checked again
 * when they go out, as before (B12.1).
 *
 * @phpstan-type CalendarPost array{id: string, status: string, body: string, time: string|null, channels: list<string>, thumb: string|null, kind: string|null, movable: bool}
 * @phpstan-type Day array{date: string, today: bool, posts: list<CalendarPost>}
 * @phpstan-type Calendar array{view: 'week'|'month', date: string, from: string, to: string, previous: string, next: string, timezone: string, days: list<Day>, scheduled: int}
 */
final readonly class SocialCalendar
{
    public const TIMEZONE = 'America/Chicago';

    /** Statuses that can move to another day. */
    public const MOVABLE = ['draft', 'scheduled', 'approval'];

    public function __construct(private SocialPosts $posts) {}

    /** @return Calendar */
    public function view(User $agent, string $view, ?string $date): array
    {
        $tz = $this->timezone($agent);
        $anchor = self::day($date, $tz) ?? CarbonImmutable::now($tz)->startOfDay();
        [$from, $to] = $view === 'month'
            ? [$anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay()]
            : [$anchor->startOfWeek(CarbonImmutable::SUNDAY), $anchor->startOfWeek(CarbonImmutable::SUNDAY)->addDays(6)];
        $start = $from->utc();
        $end = $to->endOfDay()->utc();

        $posts = SocialPost::query()->where('user_id', $agent->id)->with('targets')
            ->where(static fn ($q) => $q
                ->where(static fn ($s) => $s->whereIn('status', ['scheduled', 'approval'])->whereBetween('scheduled_at', [$start, $end]))
                ->orWhere(static fn ($s) => $s->whereNotNull('published_at')->whereBetween('published_at', [$start, $end]))
                ->orWhere(static fn ($s) => $s->whereIn('status', ['draft', 'approval'])->whereNull('scheduled_at')->whereBetween('planned_on', [$from->toDateString(), $to->toDateString()])))
            ->orderByRaw('coalesce(scheduled_at, published_at) asc')->limit(500)->get();
        $byDay = [];

        foreach ($posts as $post) {
            $at = $post->scheduled_at ?? $post->published_at;
            $day = $at !== null ? $at->setTimezone($tz)->toDateString() : $post->planned_on?->toDateString();

            if ($day !== null) {
                $byDay[$day][] = $this->post($post, $tz);
            }
        }

        $days = [];
        $today = CarbonImmutable::now($tz)->toDateString();

        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days[] = ['date' => $d->toDateString(), 'today' => $d->toDateString() === $today, 'posts' => $byDay[$d->toDateString()] ?? []];
        }

        return [
            'view' => $view === 'month' ? 'month' : 'week',
            'date' => $anchor->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'previous' => ($view === 'month' ? $anchor->subMonthNoOverflow() : $anchor->subWeek())->toDateString(),
            'next' => ($view === 'month' ? $anchor->addMonthNoOverflow() : $anchor->addWeek())->toDateString(),
            'timezone' => $tz,
            'days' => $days,
            'scheduled' => $posts->whereIn('status', ['scheduled', 'approval'])->count(),
        ];
    }

    /**
     * Another day: a scheduled post keeps its time of day; a draft is planned for the day.
     *
     * @return CalendarPost
     */
    public function move(User $agent, string $uuid, string $date): array
    {
        $tz = $this->timezone($agent);
        $day = self::day($date, $tz) ?? throw ValidationException::withMessages(['date' => self::line('social.calendar.errors.date')]);
        $post = SocialPost::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('targets')->firstOrFail();

        if (! in_array($post->status, self::MOVABLE, true)) {
            throw ValidationException::withMessages(['post' => self::line('social.calendar.errors.move')]);
        }

        if ($post->scheduled_at !== null) {
            $local = $post->scheduled_at->setTimezone($tz);
            $at = $day->setTime($local->hour, $local->minute);

            if ($at->lt(CarbonImmutable::now()->addMinutes(5))) {
                throw ValidationException::withMessages(['date' => self::line('social.calendar.errors.past')]);
            }

            $post->forceFill(['scheduled_at' => $at->utc()])->save();
        } else {
            if ($day->lt(CarbonImmutable::now($tz)->startOfDay())) {
                throw ValidationException::withMessages(['date' => self::line('social.calendar.errors.past')]);
            }

            $post->forceFill(['planned_on' => $day->toDateString()])->save();
        }

        return $this->post($post->refresh()->load('targets'), $tz);
    }

    /**
     * Use a post again: a new draft with the same words, media and places, planned for the day.
     *
     * @return CalendarPost
     */
    public function reuse(User $agent, string $uuid, string $date): array
    {
        $tz = $this->timezone($agent);
        $day = self::day($date, $tz) ?? throw ValidationException::withMessages(['date' => self::line('social.calendar.errors.date')]);

        if ($day->lt(CarbonImmutable::now($tz)->startOfDay())) {
            throw ValidationException::withMessages(['date' => self::line('social.calendar.errors.past')]);
        }

        $copy = $this->posts->repost($agent, $uuid);
        $post = SocialPost::query()->where('uuid', $copy['id'])->with('targets')->firstOrFail();
        $post->forceFill(['planned_on' => $day->toDateString()])->save();

        return $this->post($post, $tz);
    }

    public function timezone(User $agent): string
    {
        $tz = AgentProfile::query()->where('user_id', $agent->id)->value('brief_timezone');

        return is_string($tz) && in_array($tz, timezone_identifiers_list(), true) ? $tz : self::TIMEZONE;
    }

    /** @return CalendarPost */
    private function post(SocialPost $post, string $tz): array
    {
        $first = $post->media[0] ?? null;
        $path = is_array($first) ? ($first['path'] ?? null) : null;
        $at = $post->scheduled_at ?? $post->published_at;

        return [
            'id' => $post->uuid,
            'status' => $post->status,
            'body' => Str::limit($post->body, 120),
            'time' => $at?->setTimezone($tz)->format('H:i'),
            'channels' => array_values(array_unique(array_map(static fn (SocialPostTarget $t): string => $t->channel, $post->targets->all()))),
            'thumb' => is_string($path) ? URL::temporarySignedRoute('social.media', now()->addHours(SocialPosts::MEDIA_HOURS), ['path' => $path]) : (is_array($first) && $first['url'] !== '' ? $first['url'] : null),
            'kind' => is_array($first) ? $first['kind'] : null,
            'movable' => in_array($post->status, self::MOVABLE, true),
        ];
    }

    private static function day(?string $date, string $tz): ?CarbonImmutable
    {
        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tz);

        return $day instanceof CarbonImmutable && $day->toDateString() === $date ? $day : null;
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
