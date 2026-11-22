<?php

declare(strict_types=1);

namespace App\Services\StateRules;

use App\Enums\LicenceStatus;
use App\Models\Licence;
use App\Models\State;
use App\Models\StateRule;
use App\Models\User;
use App\Notifications\StateRulesChanged;
use App\Services\Audit\AuditLogger;
use App\StateRules\RuleCatalogue;
use App\StateRules\RuleTopic;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Regulator rules per state (A5.2, ADR 0019; web journey screen 62).
 *
 * - A rule is a key from the catalogue with a value, its source and the date
 *   it takes effect. Changes are drafts until "Publish changes"; each
 *   publish adds a version and never edits an old one.
 * - The rule in force on a date is the newest published version whose
 *   dates contain that date, so a change scheduled for 1 September changes
 *   nothing before then.
 * - Publishing a CE change tells every licence holder in that state what
 *   changed and from when (addition 2).
 */
final readonly class StateRules
{
    public const AUDIT_SUBJECT = 'state_rule';

    public function __construct(private AuditLogger $audit) {}

    /**
     * The rules in force in a state on a date, by key.
     *
     * @return array<string, StateRule>
     */
    public function inForce(string $state, CarbonInterface $on): array
    {
        $day = $on->toDateString();
        $rules = StateRule::query()
            ->where('state_code', $state)
            ->where('status', StateRule::PUBLISHED)
            ->whereDate('effective_from', '<=', $day)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $day))
            ->orderByDesc('version')
            ->get();

        $byKey = [];

        foreach ($rules as $rule) {
            $byKey[$rule->key] ??= $rule;
        }

        return $byKey;
    }

    /**
     * Every version of every rule in a state, newest first.
     *
     * @return Collection<int, StateRule>
     */
    public function versions(string $state): Collection
    {
        return StateRule::query()->with('publisher')->where('state_code', $state)->orderBy('key')->orderByDesc('version')->get();
    }

    /** True when a state has published licensing, exam and CE rules (the launch checklist's first item). */
    public function loaded(string $state): bool
    {
        $topics = array_filter(DB::table('state_rules')->where('state_code', $state)->where('status', StateRule::PUBLISHED)->distinct()->pluck('topic')->all(), 'is_string');

        return array_diff([RuleTopic::Licensing->value, RuleTopic::Exam->value, RuleTopic::Ce->value], $topics) === [];
    }

    /**
     * Saves a change as a draft (one draft per rule; saving again replaces it).
     *
     * @param  array<string, mixed>  $input  value, citation, source_url, effective_from, note
     *
     * @throws ValidationException
     */
    public function draft(State $state, string $key, array $input, User $actor): StateRule
    {
        $definition = RuleCatalogue::find($key) ?? throw ValidationException::withMessages(['key' => __('stateRules.errors.unknown')]);

        /** @var array{value: array<string, mixed>, citation: string, source_url: string|null, effective_from: string, note: string|null} $valid */
        $valid = Validator::make($input, [
            'value' => ['required', 'array'],
            ...$definition['type']->validation(),
            'citation' => ['required', 'string', 'max:200'],
            'source_url' => ['nullable', 'url:https', 'max:500'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $draft = StateRule::query()->where('state_code', $state->code)->where('key', $key)->where('status', StateRule::DRAFT)->first()
            ?? new StateRule([
                'state_code' => $state->code,
                'key' => $key,
                'version' => self::nextVersion($state->code, $key),
                'status' => StateRule::DRAFT,
            ]);

        $draft->fill([
            'topic' => $definition['topic'],
            'type' => $definition['type'],
            'value' => self::clean($valid['value']),
            'citation' => $valid['citation'],
            'source_url' => $valid['source_url'] ?? null,
            'effective_from' => $valid['effective_from'],
            'checked_on' => CarbonImmutable::today(),
            'note' => $valid['note'] ?? null,
            'created_by' => $actor->id,
        ])->save();

        $this->audit->record('state_rule.drafted', self::AUDIT_SUBJECT, $state->code.':'.$key, ['version' => $draft->version], $actor);

        return $draft;
    }

    public function discard(StateRule $draft, User $actor): void
    {
        if ($draft->status !== StateRule::DRAFT) {
            throw ValidationException::withMessages(['rule' => __('stateRules.errors.notDraft')]);
        }

        $draft->delete();
        $this->audit->record('state_rule.discarded', self::AUDIT_SUBJECT, $draft->state_code.':'.$draft->key, ['version' => $draft->version], $actor);
    }

    /**
     * "Publish changes": every draft in the state becomes a published version.
     *
     * @return int how many rules changed
     */
    public function publish(State $state, User $actor): int
    {
        $drafts = StateRule::query()->where('state_code', $state->code)->where('status', StateRule::DRAFT)->get();

        if ($drafts->isEmpty()) {
            return 0;
        }

        $before = [];

        DB::transaction(function () use ($drafts, $actor, $state, &$before): void {
            foreach ($drafts as $draft) {
                $previous = $this->inForce($state->code, $draft->effective_from)[$draft->key] ?? null;
                $before[$draft->key] = $previous?->value;
                $draft->forceFill(['status' => StateRule::PUBLISHED, 'published_by' => $actor->id, 'published_at' => CarbonImmutable::now()])->save();
            }
        });

        $this->audit->record('state_rule.published', self::AUDIT_SUBJECT, $state->code, [
            'before' => $before,
            'after' => $drafts->mapWithKeys(static fn (StateRule $rule): array => [$rule->key => $rule->value])->all(),
        ], $actor);

        $ce = $drafts->filter(static fn (StateRule $rule): bool => $rule->topic === RuleTopic::Ce);

        if ($ce->isNotEmpty()) {
            $this->tellLicenceHolders($state, $ce);
        }

        return $drafts->count();
    }

    /**
     * Rule change alert: everyone holding a licence in the state hears what
     * changed and from when, in their language.
     *
     * @param  Collection<int, StateRule>  $changed
     */
    private function tellLicenceHolders(State $state, Collection $changed): void
    {
        $counting = array_map(static fn (LicenceStatus $s): string => $s->value, array_filter(LicenceStatus::cases(), static fn (LicenceStatus $s): bool => $s->counts()));
        $keys = array_values($changed->map(static fn (StateRule $rule): string => $rule->key)->all());
        $from = $changed->min(static fn (StateRule $rule): string => $rule->effective_from->toDateString());

        Licence::query()->with('user')->where('state_code', $state->code)->whereIn('status', $counting)
            ->chunkById(200, static function ($licences) use ($state, $keys, $from): void {
                foreach ($licences as $licence) {
                    $licence->user->notify(new StateRulesChanged($state, $keys, is_string($from) ? $from : ''));
                }
            });
    }

    private static function nextVersion(string $state, string $key): int
    {
        $max = StateRule::query()->where('state_code', $state)->where('key', $key)->max('version');

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    /** Value fields that hold numbers. */
    private const NUMBERS = ['number', 'hours', 'fee_usd', 'days', 'qualifying_hours'];

    /**
     * Numbers as numbers and lists re-indexed, so stored values are tidy.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function clean(array $value): array
    {
        $tidy = [];

        foreach ($value as $key => $item) {
            $tidy[$key] = match (true) {
                is_array($item) => self::clean($item),
                is_string($key) && in_array($key, self::NUMBERS, true) && is_numeric($item) => $item + 0,
                default => $item,
            };
        }

        return array_is_list($value) ? array_values($tidy) : $tidy;
    }
}
