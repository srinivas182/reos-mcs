<?php

declare(strict_types=1);

namespace App\Crm;

use App\Crm\Insights\ContactInsights;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactInsight;
use App\Models\ContactNote;
use App\Models\Tag;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The CRM hub (web screen 13, phone contact list, B8.3): an agent's
 * contacts from every source in one list, by segment or tag, with the last
 * touch and the AI next step while it's fresh. Only the agent's own
 * contacts: a broker never sees them (journey rule).
 *
 * @phpstan-type Row array{contact: Contact, segment: string|null, last_touch_at: string|null, insight: ContactInsight|null}
 */
final readonly class ContactHub
{
    public const PAGE = 100;

    public function __construct(private ContactInsights $insights) {}

    /**
     * @return array{rows: list<Row>, counts: array<string, int>, tags: list<string>, total: int}
     */
    public function list(User $agent, string $segment = Segments::ALL, ?string $tag = null, ?string $search = null, int $limit = self::PAGE): array
    {
        $query = Segments::apply($this->mine($agent), $segment);

        if ($tag !== null && $tag !== '') {
            $query->whereHas('tags', static fn (Builder $q) => $q->where('name', $tag));
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.mb_strtolower(addcslashes($search, '%_\\')).'%';
            $digits = (string) preg_replace('/\D+/', '', $search);
            $query->where(static function (Builder $q) use ($like, $digits): void {
                $q->whereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(CAST(emails AS TEXT)) LIKE ?', [$like]);

                if (strlen($digits) >= 3) {
                    $q->orWhereRaw('CAST(phones AS TEXT) LIKE ?', ['%'.$digits.'%']);
                }
            });
        }

        $total = (clone $query)->count();
        $contacts = $query->with(['links', 'tags'])
            ->addSelect(['contacts.*', 'last_note_at' => ContactNote::query()->selectRaw('max(created_at)')->whereColumn('contact_id', 'contacts.id')])
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(max(1, min($limit, 500)))->get();

        return ['rows' => $this->rows($agent, $contacts), 'counts' => $this->counts($agent), 'tags' => $this->tags($agent), 'total' => $total];
    }

    /**
     * Everything the contact panel shows: tasks, activity and the insight
     * (notes come with the contact, as on the phone).
     *
     * @return array{tasks: Collection<int, Todo>, activity: Collection<int, ContactActivity>, notes: Collection<int, ContactNote>, insight: ContactInsight|null, segment: string|null, last_touch_at: string|null}
     */
    public function panel(User $agent, Contact $contact): array
    {
        $notes = $contact->notes()->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();
        $tasks = Todo::query()->where('user_id', $agent->id)->where('related_type', 'contact')->where('related_id', $contact->uuid)
            ->orderByRaw('done_at is not null')->orderBy('due_date')->orderByDesc('id')->limit(50)->get();

        return [
            'notes' => $notes,
            'tasks' => $tasks,
            'activity' => ContactActivity::query()->where('contact_id', $contact->id)->with('contact')->orderByDesc('occurred_at')->orderByDesc('id')->limit(20)->get(),
            'insight' => $this->insights->fresh($contact, ContactInsights::language($agent)),
            'segment' => Segments::of($contact->stage),
            'last_touch_at' => ContactInsights::lastTouch($notes->first()?->created_at, $tasks->max('done_at'))?->toIso8601String(),
        ];
    }

    /** One of the agent's own contacts, or null. */
    public function find(User $agent, string $uuid): ?Contact
    {
        return $this->mine($agent)->where('uuid', $uuid)->first();
    }

    /** @return Builder<Contact> */
    private function mine(User $agent): Builder
    {
        return Contact::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $agent->id);
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<Row>
     */
    private function rows(User $agent, Collection $contacts): array
    {
        $fresh = $this->insights->freshFor($contacts, ContactInsights::language($agent));
        $done = Todo::query()->where('user_id', $agent->id)->where('related_type', 'contact')->whereIn('related_id', $contacts->pluck('uuid')->all())
            ->whereNotNull('done_at')->groupBy('related_id')->selectRaw('related_id, max(done_at) as last')->pluck('last', 'related_id');
        $rows = [];

        foreach ($contacts as $contact) {
            $rows[] = [
                'contact' => $contact,
                'segment' => Segments::of($contact->stage),
                'last_touch_at' => ContactInsights::lastTouch($contact->getAttribute('last_note_at'), $done[$contact->uuid] ?? null)?->toIso8601String(),
                'insight' => $fresh[$contact->id] ?? null,
            ];
        }

        return $rows;
    }

    /** @return array<string, int> Contacts per tab, "all" included. */
    private function counts(User $agent): array
    {
        $stages = $this->mine($agent)->toBase()->select('stage')->selectRaw('count(*) as total')->groupBy('stage')->get();
        $counts = array_fill_keys(Segments::names(), 0);

        foreach ($stages as $row) {
            $total = is_numeric($row->total) ? (int) $row->total : 0;
            $segment = Segments::of(is_string($row->stage) ? $row->stage : null);
            $counts[Segments::ALL] += $total;

            if ($segment !== null) {
                $counts[$segment] += $total;
            }
        }

        return $counts;
    }

    /** @return list<string> The agent's own tags, from every source, as tabs. */
    private function tags(User $agent): array
    {
        return array_values(Tag::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $agent->id)
            ->whereExists(static fn ($q) => $q->selectRaw('1')->from('contact_tag')->whereColumn('contact_tag.tag_id', 'tags.id'))
            ->orderBy('name')->limit(30)->get()->map(static fn (Tag $tag): string => $tag->name)->all());
    }
}
