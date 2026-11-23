<?php

declare(strict_types=1);

namespace App\Crm\Insights;

use App\Crm\Segments;
use App\Crm\Sources;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactInsight;
use App\Models\ContactLink;
use App\Models\ContactNote;
use App\Models\Todo;
use App\Models\User;
use App\Services\Ai\AiCredits;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A contact's AI summary and next step (B8.3, ADR 0034). Free for agents
 * (decision 2): logged at 0 credits, within the hourly AI limit, and kept
 * until the contact changes: a new note, task, sync change or stage, or
 * the agent's language.
 *
 * @phpstan-import-type Brief from InsightWriter
 */
final readonly class ContactInsights
{
    public const FEATURE = 'crm_insight';

    public function __construct(private InsightWriters $writers, private AiCredits $credits, private Sources $sources) {}

    /** The kept insight, while the contact hasn't changed since. */
    public function fresh(Contact $contact, string $language): ?ContactInsight
    {
        $insight = ContactInsight::query()->where('contact_id', $contact->id)->first();

        return $insight !== null && $insight->fingerprint === $this->fingerprints(collect([$contact]), $language)[$contact->id] ? $insight : null;
    }

    /**
     * Kept insights still fresh for a page of contacts, by contact id.
     *
     * @param  Collection<int, Contact>  $contacts
     * @return array<int, ContactInsight>
     */
    public function freshFor(Collection $contacts, string $language): array
    {
        if ($contacts->isEmpty()) {
            return [];
        }

        $prints = $this->fingerprints($contacts, $language);
        $fresh = [];

        foreach (ContactInsight::query()->whereIn('contact_id', $contacts->pluck('id'))->get() as $insight) {
            if (($prints[$insight->contact_id] ?? null) === $insight->fingerprint) {
                $fresh[$insight->contact_id] = $insight;
            }
        }

        return $fresh;
    }

    /**
     * The insight, written now unless the kept one is still fresh. Over the
     * hourly AI limit: the kept one if any, else a 429.
     */
    public function generate(Contact $contact, User $agent): ContactInsight
    {
        $language = self::language($agent);
        $fresh = $this->fresh($contact, $language);

        if ($fresh !== null) {
            return $fresh;
        }

        if (! $this->credits->withinHourlyLimit($agent)) {
            throw new HttpException(429, __('crm.insight.limit'));
        }

        $writer = $this->writers->writer();

        try {
            $written = $writer->write($this->brief($contact, $agent, $language));
        } catch (Throwable $e) {
            // Claude unavailable or off-script: the simulator still gives a useful step.
            Log::warning('crm.insight.failed', ['contact' => $contact->id, 'error' => $e->getMessage()]);
            $writer = new SimulatedInsightWriter;
            $written = $writer->write($this->brief($contact, $agent, $language));
        }

        $this->credits->logFree($agent, self::FEATURE, $writer->engine());

        return ContactInsight::query()->updateOrCreate(['contact_id' => $contact->id], [
            ...$written,
            'language' => $language,
            'fingerprint' => $this->fingerprints(collect([$contact]), $language)[$contact->id],
            'engine' => $writer->engine(),
            'generated_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Today's "do this now" (Agent App Journey screen 6): the open or new
     * contact most in need of a touch, with an overdue task first. At most
     * one insight is written per call.
     *
     * @return array{contact: Contact, insight: ContactInsight}|null
     */
    public function today(User $agent): ?array
    {
        $base = Contact::query()->where('owner_type', 'user')->where('owner_id', $agent->id);
        $overdue = Todo::query()->where('user_id', $agent->id)->where('related_type', 'contact')->whereNull('done_at')
            ->where(fn ($q) => $q->where('due_date', '<=', CarbonImmutable::today()->toDateString())->orWhere('due_at', '<=', CarbonImmutable::now()))
            ->orderBy('due_date')->orderBy('due_at')->value('related_id');
        $contact = is_string($overdue) ? (clone $base)->where('uuid', $overdue)->first() : null;

        if ($contact === null) {
            $lastNote = ContactNote::query()->selectRaw('max(created_at)')->whereColumn('contact_id', 'contacts.id');
            $contact = Segments::apply(clone $base, 'active')->addSelect(['last_note_at' => $lastNote])
                ->orderByRaw('last_note_at asc nulls first')->orderBy('id')->first()
                ?? Segments::apply(clone $base, 'new')->latest('created_at')->first();
        }

        if ($contact === null) {
            return null;
        }

        try {
            return ['contact' => $contact, 'insight' => $this->generate($contact, $agent)];
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * What the writer reads: the contact from every source.
     *
     * @return Brief
     */
    public function brief(Contact $contact, User $agent, string $language): array
    {
        $notes = ContactNote::query()->where('contact_id', $contact->id)->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get();
        $tasks = Todo::query()->where('related_type', 'contact')->where('related_id', $contact->uuid)->where('user_id', $agent->id)->orderByDesc('updated_at')->limit(10)->get();
        $activity = ContactActivity::query()->where('contact_id', $contact->id)->orderByDesc('occurred_at')->limit(10)->get();
        $touch = self::lastTouch($notes->first()?->created_at, $tasks->max('done_at'));

        return [
            'agent' => $agent->name ?? '',
            'language' => $language,
            'today' => CarbonImmutable::today()->toDateString(),
            'name' => $contact->name(),
            'first_name' => (string) $contact->first_name,
            'stage' => $contact->stage,
            'segment' => Segments::of($contact->stage),
            'sources' => array_values(array_unique($contact->links()->get()->map(fn (ContactLink $link): string => $this->sources->name($link->source))->all())),
            'has_phone' => $contact->phones !== [],
            'has_email' => $contact->emails !== [],
            'days_since_touch' => $touch === null ? null : (int) $touch->diffInDays(CarbonImmutable::now()),
            'notes' => array_values($notes->map(fn (ContactNote $n): array => ['at' => (string) ($n->remote_updated_at ?? $n->created_at)?->toDateString(), 'source' => $this->sources->name($n->source), 'text' => mb_substr($n->body, 0, 600)])->all()),
            'tasks' => array_values($tasks->map(static fn (Todo $t): array => ['title' => $t->title, 'due' => ($t->due_date ?? $t->due_at)?->toDateString(), 'done' => $t->done_at !== null])->all()),
            'activity' => array_values($activity->map(static fn (ContactActivity $a): array => ['at' => $a->occurred_at->toDateString(), 'text' => trim($a->kind.' '.($a->details['field'] ?? ''))])->all()),
        ];
    }

    /** The newest of a note and a finished task: the last time the agent touched the person. */
    public static function lastTouch(mixed ...$times): ?CarbonImmutable
    {
        $latest = null;

        foreach ($times as $time) {
            if ($time instanceof \DateTimeInterface) {
                $at = CarbonImmutable::instance($time);
                $latest = $latest === null || $at->greaterThan($latest) ? $at : $latest;
            } elseif (is_string($time) && $time !== '') {
                $at = CarbonImmutable::parse($time);
                $latest = $latest === null || $at->greaterThan($latest) ? $at : $latest;
            }
        }

        return $latest;
    }

    public static function language(User $user): string
    {
        return $user->locale->value;
    }

    /**
     * What an insight was written from, per contact: any new note, task,
     * sync change or stage makes it stale.
     *
     * @param  Collection<int, Contact>  $contacts
     * @return array<int, string>
     */
    private function fingerprints(Collection $contacts, string $language): array
    {
        $ids = $contacts->pluck('id')->all();
        $notes = ContactNote::query()->whereIn('contact_id', $ids)->groupBy('contact_id')->selectRaw('contact_id, max(id) as last, count(*) as total')->get()->keyBy('contact_id');
        $activity = ContactActivity::query()->whereIn('contact_id', $ids)->groupBy('contact_id')->selectRaw('contact_id, max(id) as last')->pluck('last', 'contact_id');
        $tasks = Todo::withTrashed()->where('related_type', 'contact')->whereIn('related_id', $contacts->pluck('uuid')->all())->groupBy('related_id')
            ->selectRaw('related_id, max(updated_at) as last, count(*) as total')->get()->keyBy('related_id');
        $prints = [];

        foreach ($contacts as $contact) {
            $note = $notes->get($contact->id);
            $task = $tasks->get($contact->uuid);
            $prints[$contact->id] = hash('sha256', json_encode([
                $contact->stage, $contact->updated_at?->toIso8601String(), $language,
                $note?->getAttribute('last'), $note?->getAttribute('total'), $activity[$contact->id] ?? null,
                $task?->getAttribute('last'), $task?->getAttribute('total'),
            ], JSON_THROW_ON_ERROR));
        }

        return $prints;
    }
}
