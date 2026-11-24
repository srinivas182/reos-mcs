<?php

declare(strict_types=1);

namespace App\Studio\Celebrations;

use App\Licences\Ce\CeWallet;
use App\Models\CeCompletion;
use App\Models\Celebration;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\LicenceRenewal;
use App\Models\MlsListing;
use App\Models\MlsMemberLink;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\CelebrationReady;
use App\Social\SocialPosts;
use App\Studio\Studio;
use App\Studio\StudioGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Celebration moments (B12.3, ADR 0052; agent screen 163).
 *
 * Milestones are found from what REOS already knows: a listing that went
 * active (just listed), a deal under contract, a closed deal (just sold,
 * or the first closing), CE complete for the period, a license renewed,
 * and the anniversary of being licensed. Each becomes a free post (three
 * captions, three designs with the badge and the agent's photo), kept as
 * a draft in My posts. At most one is shown (and notified) a day; the
 * rest wait. Clients are never named: a deal names the property only.
 *
 * @phpstan-import-type GenerationRow from Studio
 *
 * @phpstan-type CelebrationRow array{id: string, milestone: string, label: string|null, post: string|null, generation: GenerationRow|null, shown_at: string|null, created_at: string|null}
 */
final readonly class Celebrations
{
    public const MILESTONES = ['just_listed', 'under_contract', 'just_sold', 'first_closing', 'ce_complete', 'license_renewed', 'anniversary'];

    /** How far back a milestone still counts. */
    public const RECENT_DAYS = 14;

    public function __construct(private StudioGenerator $generator, private SocialPosts $posts, private CeWallet $wallet) {}

    /**
     * Every agent with a license: find new milestones and show the day's one.
     *
     * @return int how many were shown
     */
    public function run(?CarbonImmutable $now = null): int
    {
        $shown = 0;

        foreach (User::query()->whereIn('id', Licence::query()->select('user_id'))->cursor() as $agent) {
            try {
                $this->scan($agent, $now);
                $shown += $this->showToday($agent, $now) !== null ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $shown;
    }

    /**
     * New milestones for one agent, each made into a post once.
     *
     * @return list<Celebration>
     */
    public function scan(User $agent, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(self::RECENT_DAYS);
        $made = [];
        $found = [];

        // Just listed: the agent's own MLS listings that went active lately.
        $keys = MlsMemberLink::query()->where('user_id', $agent->id)->where('status', MlsMemberLink::CONFIRMED)->get(['integration', 'member_key']);

        foreach ($keys->groupBy('integration') as $integration => $members) {
            foreach (MlsListing::query()->where('integration', $integration)->whereIn('list_agent_key', $members->pluck('member_key'))->where('status', 'active')->where('listed_on', '>=', $since->toDateString())->get() as $listing) {
                $found[] = ['just_listed', "listing:{$listing->uuid}", trim(implode(', ', array_filter([$listing->street, $listing->city])))];
            }
        }

        // Deals: under contract, and closings (the first one is its own milestone). Property only, never the clients.
        $closings = Deal::query()->where('user_id', $agent->id)->where('stage', 'closed')->count();

        foreach (Deal::query()->where('user_id', $agent->id)->where(static fn ($q) => $q->where(static fn ($c) => $c->where('stage', 'under_contract')->where('updated_at', '>=', $since))->orWhere(static fn ($c) => $c->where('stage', 'closed')->where('closed_at', '>=', $since)))->get() as $deal) {
            $milestone = $deal->stage === 'under_contract' ? 'under_contract' : ($closings === 1 ? 'first_closing' : 'just_sold');
            $found[] = [$milestone, ($deal->stage === 'under_contract' ? 'contract:' : 'closing:').$deal->uuid, $deal->property];
        }

        foreach (Licence::query()->where('user_id', $agent->id)->get() as $licence) {
            // CE complete for this period, with a course finished lately.
            $wallet = $this->wallet->wallet($licence, $now);

            if ($wallet !== null && $wallet['ce']['complete'] && CeCompletion::query()->where('user_id', $agent->id)->where('completed_on', '>=', $since->toDateString())->exists()) {
                $found[] = ['ce_complete', "ce:{$licence->id}:".($wallet['renewal']['due_on'] ?? $now->year), null];
            }

            foreach (LicenceRenewal::query()->where('licence_id', $licence->id)->where('renewed_at', '>=', $since)->get() as $renewal) {
                $found[] = ['license_renewed', "renewal:{$renewal->id}", null];
            }

            // The anniversary of being licensed, in the week after it.
            if ($licence->licensed_on !== null) {
                $years = $now->year - $licence->licensed_on->year;
                $day = CarbonImmutable::instance($licence->licensed_on)->setYear($now->year);

                if ($years >= 1 && $day->betweenIncluded($now->subDays(7)->startOfDay(), $now->endOfDay())) {
                    $found[] = ['anniversary', "anniversary:{$now->year}", (string) $years];
                }
            }
        }

        foreach ($found as [$milestone, $subject, $label]) {
            $celebration = $this->record($agent, $milestone, $subject, $label);

            if ($celebration !== null) {
                $made[] = $celebration;
            }
        }

        return $made;
    }

    /**
     * One milestone made into a post, once. Null when it was already made.
     */
    public function record(User $agent, string $milestone, string $subject, ?string $label): ?Celebration
    {
        if (Celebration::query()->where('user_id', $agent->id)->where('subject', $subject)->exists()) {
            return null;
        }

        $words = $milestone === 'anniversary'
            ? self::line('studio.celebrate.years', ['years' => (string) $label], $agent->locale->value)
            : ($label ?? self::line("studio.celebrate.badges.{$milestone}", [], $agent->locale->value));
        $generation = $this->generator->celebrate($agent, $milestone, $words);
        $draft = $this->posts->create($agent, $generation->captions[0]['text'], [$generation->designs[0]['path']], null, [], 'draft', null, null);

        try {
            return DB::transaction(static fn (): Celebration => Celebration::query()->create([
                'uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'milestone' => $milestone, 'subject' => $subject, 'label' => $words,
                'studio_generation_id' => $generation->id, 'social_post_id' => SocialPost::query()->where('uuid', $draft['id'])->value('id'),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Today's celebration: the one shown today, or the oldest waiting one, shown and notified now.
     */
    public function showToday(User $agent, ?CarbonImmutable $now = null): ?Celebration
    {
        $now ??= CarbonImmutable::now();
        $start = $now->startOfDay();

        if (Celebration::query()->where('user_id', $agent->id)->where('shown_at', '>=', $start)->exists()) {
            return null;
        }

        $next = Celebration::query()->where('user_id', $agent->id)->whereNull('shown_at')->oldest('id')->first();

        if ($next === null) {
            return null;
        }

        $next->forceFill(['shown_at' => $now])->save();
        $agent->notify(new CelebrationReady($next));

        return $next;
    }

    /**
     * What My posts shows: today's celebration and the ones waiting.
     *
     * @return array{today: CelebrationRow|null, waiting: list<CelebrationRow>}
     */
    public function list(User $agent, ?CarbonImmutable $now = null): array
    {
        $start = ($now ?? CarbonImmutable::now())->startOfDay();
        $rows = Celebration::query()->where('user_id', $agent->id)->with('generation', 'post')->latest('id')->limit(30)->get();
        $today = $rows->first(static fn (Celebration $c): bool => $c->shown_at !== null && $c->shown_at->gte($start));

        return [
            'today' => $today === null ? null : self::row($today),
            'waiting' => array_values($rows->filter(static fn (Celebration $c): bool => $c->shown_at === null)->map(self::row(...))->all()),
        ];
    }

    public function own(User $agent, string $uuid): Celebration
    {
        return Celebration::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('generation', 'post')->firstOrFail();
    }

    /** @return CelebrationRow */
    public static function row(Celebration $c): array
    {
        return [
            'id' => $c->uuid,
            'milestone' => $c->milestone,
            'label' => $c->label,
            'post' => $c->post?->uuid,
            'generation' => $c->generation === null ? null : Studio::row($c->generation),
            'shown_at' => $c->shown_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = [], ?string $language = null): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
