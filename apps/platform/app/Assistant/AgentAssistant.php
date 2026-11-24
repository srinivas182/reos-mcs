<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Agents\AgentBriefs;
use App\Agents\AgentToday;
use App\Deals\Deals;
use App\Models\AgentProfile;
use App\Models\AssistantChat;
use App\Models\AssistantMessage;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactNote;
use App\Models\Deal;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Bedrock;
use App\Services\Audit\AuditLogger;
use App\VirtualBroker\VirtualBroker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The agent's AI assistant (A12.3, ADR 0049; agent screens 59 and 60).
 *
 * It decides which knowledge a question needs:
 *
 * - **Today** ("What should I do today?"): Today's ranked list (A11.1b) and
 *   the morning brief's first call (A11.1c), from the agent's CRM, deals,
 *   calendar and plan, each item with its source;
 * - **Prep** an appointment: the next one, with the contact's CRM summary,
 *   latest notes and activity, and their deal;
 * - **Deal:** the deal named, its stage and next dates;
 * - **Draft** an email or text to a contact: in the agent's voice, from the
 *   CRM; opened in Mail or Messages, never sent by REOS;
 * - **Policy:** handed to the virtual broker (A12.1), with its sources;
 * - **General:** Claude, for credits; without keys, what it can help with.
 *
 * Today, prep, deal and drafts read the agent's own synced data, and only
 * once the agent has allowed it (one switch, audited). Answers from their
 * data are free; a Claude draft costs `ai_suggestion`, a general answer
 * `general_answer`.
 *
 * @phpstan-type Item array{title: string, detail: string|null, source: string|null, at: string|null, link: string|null}
 * @phpstan-type Draft array{channel: 'email'|'text', to: string, name: string, subject: string|null, body: string, href: string}
 * @phpstan-type Reply array{body: string, kind: string, items: list<Item>|null, draft: Draft|null, broker_question: string|null, engine: string}
 * @phpstan-type MessageRow array{id: int, role: string, body: string, kind: string|null, items: list<Item>, draft: Draft|null, broker_question: string|null, created_at: string|null}
 * @phpstan-type ChatRow array{id: string, title: string, updated_at: string|null, messages: list<MessageRow>}
 */
final readonly class AgentAssistant
{
    public const FEATURE = 'assistant';

    /** Ask home's starters (screen 59), in order. */
    public const STARTERS = ['virtual_broker', 'review_contract', 'prepare_contract', 'write_ad', 'reply_emails', 'prep_appointment'];

    /** A drafting verb, then a name ("Text Maria…", "Write an email to Ana…"). */
    private const DRAFT = '/(?i:\b(draft|write|text|email|e-mail|message|reply|escrib\w*|redact\w*|mensaje|correo)\b)[^.?!]{0,30}?\b\p{Lu}\p{Ll}+/u';

    private const TODAY = '/(what should i do|what do i do|my day\b|\btoday\b|priorit\w*|qué (debo hacer|hago) hoy|mi día|\bhoy\b)/u';

    private const PREP = '/\b(prep\w*|appointment|meeting|showing|cita|reuni[oó]n|preparar|visita)\b/u';

    private const DEAL = '/\b(deal|closing|under contract|operaci[oó]n|cierre)\b/u';

    private const POLICY = '/\b(policy|policies|allowed|rule|rules|trec|commission|split|earnest|advertis\w*|brokerage|pol[ií]tica|permitido|regla|comisi[oó]n)\b/u';

    public function __construct(
        private AgentToday $today,
        private AgentBriefs $briefs,
        private VirtualBroker $broker,
        private Bedrock $bedrock,
        private AiCredits $credits,
        private AiSettings $settings,
        private AuditLogger $audit,
    ) {}

    /** @return array{permission: bool, starters: list<string>, chats: list<array{id: string, title: string, updated_at: string|null}>} */
    public function home(User $agent): array
    {
        return [
            'permission' => $this->allowed($agent),
            'starters' => self::STARTERS,
            'chats' => array_values(AssistantChat::query()->where('user_id', $agent->id)->latest('updated_at')->latest('id')->limit(20)->get()
                ->map(static fn (AssistantChat $c): array => ['id' => $c->uuid, 'title' => $c->title, 'updated_at' => $c->updated_at?->toIso8601String()])->all()),
        ];
    }

    /** One switch: may the assistant read my CRM, deals and calendar? */
    public function allow(User $agent, bool $allowed): void
    {
        $profile = AgentProfile::query()->firstOrCreate(['user_id' => $agent->id]);
        $was = $profile->assistant_context_at !== null;
        $profile->forceFill(['assistant_context_at' => $allowed ? ($profile->assistant_context_at ?? now()) : null])->save();
        $this->audit->record('assistant.context', 'user', (string) $agent->id, ['from' => $was, 'to' => $allowed], $agent);
    }

    public function allowed(User $agent): bool
    {
        return AgentProfile::query()->where('user_id', $agent->id)->whereNotNull('assistant_context_at')->exists();
    }

    /** A question, answered in a new chat or the one given. */
    public function ask(User $agent, string $question, ?string $chatId): AssistantChat
    {
        if (! $this->credits->withinHourlyLimit($agent)) {
            throw new HttpException(429, self::line('crm.insight.limit'));
        }

        $chat = $chatId === null
            ? AssistantChat::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'title' => Str::limit(trim($question), 117)])
            : AssistantChat::query()->where('user_id', $agent->id)->where('uuid', $chatId)->firstOrFail();
        AssistantMessage::query()->create(['assistant_chat_id' => $chat->id, 'role' => 'question', 'body' => trim($question)]);

        $reply = $this->reply($agent, trim($question));

        DB::transaction(function () use ($chat, $reply): void {
            AssistantMessage::query()->create([
                'assistant_chat_id' => $chat->id, 'role' => 'answer', 'body' => $reply['body'], 'kind' => $reply['kind'], 'items' => $reply['items'],
                'draft' => $reply['draft'], 'broker_question' => $reply['broker_question'], 'engine' => $reply['engine'],
            ]);
            $chat->touch();
        });

        return $chat;
    }

    public function chat(User $agent, string $uuid): AssistantChat
    {
        return AssistantChat::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('messages')->firstOrFail();
    }

    /** @return ChatRow */
    public static function row(AssistantChat $chat): array
    {
        $messages = [];

        foreach ($chat->messages as $m) {
            $messages[] = ['id' => $m->id, 'role' => $m->role, 'body' => $m->body, 'kind' => $m->kind, 'items' => $m->items ?? [], 'draft' => $m->draft, 'broker_question' => $m->broker_question, 'created_at' => $m->created_at?->toIso8601String()];
        }

        return ['id' => $chat->uuid, 'title' => $chat->title, 'updated_at' => $chat->updated_at?->toIso8601String(), 'messages' => $messages];
    }

    /** @return Reply */
    private function reply(User $agent, string $question): array
    {
        $words = mb_strtolower($question);
        $intent = match (true) {
            preg_match(self::DRAFT, $question) === 1 => 'draft',
            preg_match(self::PREP, $words) === 1 => 'prep',
            preg_match(self::TODAY, $words) === 1 => 'today',
            preg_match(self::DEAL, $words) === 1 => 'deal',
            preg_match(self::POLICY, $words) === 1 => 'policy',
            default => 'general',
        };

        if (in_array($intent, ['today', 'prep', 'deal', 'draft'], true) && ! $this->allowed($agent)) {
            return ['body' => self::line('assistant.permission.ask'), 'kind' => 'permission', 'items' => null, 'draft' => null, 'broker_question' => null, 'engine' => 'none'];
        }

        return match ($intent) {
            'today' => $this->todayReply($agent),
            'prep' => $this->prepReply($agent),
            'deal' => $this->dealReply($agent, $question),
            'draft' => $this->draftReply($agent, $question),
            'policy' => $this->policyReply($agent, $question),
            default => $this->generalReply($agent, $question),
        };
    }

    /**
     * "What should I do today?": Today's list, ranked, from every tool, and who to call first.
     *
     * @return Reply
     */
    private function todayReply(User $agent): array
    {
        $zone = $this->briefs->settings($agent)['timezone'];
        $from = CarbonImmutable::now($zone)->startOfDay();
        $items = array_slice($this->today->ranked($agent, $from, $from->addDay()), 0, AgentToday::SHOWN);
        $brief = $this->briefs->for($agent);
        $this->credits->logFree($agent, self::FEATURE, 'data');

        if ($items === []) {
            return ['body' => self::line('assistant.today.empty'), 'kind' => 'today', 'items' => [], 'draft' => null, 'broker_question' => null, 'engine' => 'data'];
        }

        $lines = [self::line('assistant.today.intro', ['count' => (string) count($items)])];

        if ($brief['first_call'] !== null) {
            $lines[] = self::line('assistant.today.callFirst', ['name' => $brief['first_call']['name']]);
        }

        return [
            'body' => implode(' ', $lines), 'kind' => 'today', 'draft' => null, 'broker_question' => null, 'engine' => 'data',
            'items' => array_map(static fn (array $i): array => [
                'title' => $i['title'], 'detail' => trim(self::line('assistant.today.reasons.'.$i['reason']).($i['detail'] !== null ? ' · '.$i['detail'] : '')),
                'source' => $i['source'], 'at' => $i['at'], 'link' => $i['deal'] !== null ? '/deals?deal='.$i['deal'] : null,
            ], $items),
        ];
    }

    /**
     * Prep my next appointment: when, who, their CRM summary, notes, activity and deal.
     *
     * @return Reply
     */
    private function prepReply(User $agent): array
    {
        $event = CalendarEvent::query()->where('user_id', $agent->id)->where('status', '!=', 'cancelled')->where('starts_at', '>=', CarbonImmutable::now()->subMinutes(15))
            ->where('all_day', false)->orderBy('starts_at')->first();
        $this->credits->logFree($agent, self::FEATURE, 'data');

        if ($event === null) {
            return ['body' => self::line('assistant.prep.none'), 'kind' => 'prep', 'items' => [], 'draft' => null, 'broker_question' => null, 'engine' => 'data'];
        }

        $contact = $event->related_type === 'contact' && $event->related_id !== null ? $this->contacts($agent)->where('uuid', $event->related_id)->with('insight')->first() : null;
        $items = [['title' => (string) ($event->title ?? self::line('assistant.prep.appointment')), 'detail' => $event->location, 'source' => $event->source === CalendarEvent::SOURCE_REOS ? 'REOS' : $event->source, 'at' => $event->starts_at->toIso8601String(), 'link' => null]];

        if ($contact !== null) {
            if ($contact->insight !== null) {
                $items[] = ['title' => self::line('assistant.prep.summary'), 'detail' => $contact->insight->summary.' '.$contact->insight->next_step, 'source' => 'REOS', 'at' => null, 'link' => '/contacts/'.$contact->uuid];
            }

            foreach (ContactNote::query()->where('contact_id', $contact->id)->latest('id')->limit(2)->get() as $note) {
                $items[] = ['title' => self::line('assistant.prep.note'), 'detail' => Str::limit($note->body, 200), 'source' => $note->source, 'at' => $note->created_at?->toIso8601String(), 'link' => null];
            }

            $last = ContactActivity::query()->where('contact_id', $contact->id)->latest('occurred_at')->first();

            if ($last !== null) {
                $items[] = ['title' => self::line('assistant.prep.lastActivity'), 'detail' => self::line('assistant.prep.activity', ['kind' => $last->kind]), 'source' => $last->source, 'at' => $last->occurred_at->toIso8601String(), 'link' => null];
            }

            $deal = Deal::query()->where('user_id', $agent->id)->whereNotIn('stage', ['closed', 'cancelled'])->get()
                ->first(static fn (Deal $d): bool => collect($d->parties)->contains(static fn (array $p): bool => mb_strtolower($p['name']) === mb_strtolower($contact->name())));

            if ($deal !== null) {
                $items[] = ['title' => self::line('assistant.prep.deal'), 'detail' => $deal->property.' · '.self::line('assistant.deal.stages.'.$deal->stage), 'source' => $deal->source === 'reos' ? 'REOS' : $deal->source, 'at' => null, 'link' => '/deals?deal='.$deal->uuid];
            }
        }

        $who = $contact?->name() ?? (string) ($event->title ?? '');
        $starts = CarbonImmutable::instance($event->starts_at)->setTimezone($this->briefs->settings($agent)['timezone']);
        $local = $starts->locale($agent->locale->value);

        return ['body' => self::line('assistant.prep.intro', ['who' => $who, 'time' => ($local instanceof CarbonImmutable ? $local : $starts)->isoFormat('dddd LT')]), 'kind' => 'prep', 'items' => $items, 'draft' => null, 'broker_question' => null, 'engine' => 'data'];
    }

    /**
     * A deal named in the question (or the next one closing): its stage and dates.
     *
     * @return Reply
     */
    private function dealReply(User $agent, string $question): array
    {
        $deals = Deal::query()->where('user_id', $agent->id)->whereNotIn('stage', ['closed', 'cancelled'])->with('dates')->get();
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [], static fn (string $w): bool => mb_strlen($w) >= 3);
        $deal = $deals->first(static fn (Deal $d): bool => array_filter($words, static fn (string $w): bool => str_contains(mb_strtolower($d->property), $w) && ! in_array($w, ['deal', 'the', 'closing', 'when'], true)) !== [])
            ?? $deals->sortBy(static fn (Deal $d): string => $d->dates->whereNull('done_at')->map(static fn ($date): string => $date->due_on->toDateString())->min() ?? '9999')->first();
        $this->credits->logFree($agent, self::FEATURE, 'data');

        if ($deal === null) {
            return ['body' => self::line('assistant.deal.none'), 'kind' => 'deal', 'items' => [], 'draft' => null, 'broker_question' => null, 'engine' => 'data'];
        }

        $items = [];

        foreach ($deal->dates->whereNull('done_at')->sortBy('due_on') as $date) {
            $items[] = ['title' => Deals::dateName($date, $agent), 'detail' => null, 'source' => $deal->source === 'reos' ? 'REOS' : $deal->source, 'at' => $date->due_on->toDateString(), 'link' => '/deals?deal='.$deal->uuid];
        }

        return ['body' => self::line('assistant.deal.intro', ['property' => $deal->property, 'stage' => self::line('assistant.deal.stages.'.$deal->stage)]), 'kind' => 'deal', 'items' => $items, 'draft' => null, 'broker_question' => null, 'engine' => 'data'];
    }

    /**
     * An email or text to a contact, drafted from the CRM; opened in Mail or Messages.
     *
     * @return Reply
     */
    private function draftReply(User $agent, string $question): array
    {
        $contact = $this->namedContact($agent, $question);

        if ($contact === null) {
            return ['body' => self::line('assistant.draft.notFound'), 'kind' => 'draft', 'items' => null, 'draft' => null, 'broker_question' => null, 'engine' => 'none'];
        }

        $text = preg_match('/\b(text|sms|mensaje de texto|texto)\b/u', mb_strtolower($question)) === 1;
        $email = $contact->emails[0]['value'] ?? null;
        $phone = $contact->phones[0]['value'] ?? null;
        $channel = ($text && $phone !== null) || $email === null ? 'text' : 'email';
        $to = $channel === 'text' ? $phone : $email;

        if ($to === null) {
            return ['body' => self::line('assistant.draft.noWay', ['name' => $contact->name()]), 'kind' => 'draft', 'items' => null, 'draft' => null, 'broker_question' => null, 'engine' => 'none'];
        }

        $first = $contact->first_name ?? $contact->name();
        $body = null;
        $engine = 'simulator';
        $bedrock = $this->bedrock->client(20);

        if ($bedrock !== null && $this->credits->canSpend($agent, max(0, $this->settings->cost('ai_suggestion')))) {
            try {
                $body = (new ClaudeDrafter($bedrock['client'], $bedrock['model']))->draft($question, $contact, $channel, (string) $agent->name, $agent->locale->value);
                $engine = 'ai';
                $this->credits->charge($agent, self::FEATURE, 'ai', max(0, $this->settings->cost('ai_suggestion')));
            } catch (Throwable $e) {
                Log::warning('assistant.draft_failed', ['error' => $e->getMessage()]);
            }
        }

        $body ??= $contact->insight->draft ?? self::line('assistant.draft.template.'.$channel, ['name' => $first, 'agent' => (string) $agent->name]);
        $subject = $channel === 'email' ? self::line('assistant.draft.subject', ['agent' => (string) $agent->name]) : null;
        $href = $channel === 'email' ? 'mailto:'.rawurlencode($to).'?subject='.rawurlencode((string) $subject).'&body='.rawurlencode($body) : 'sms:'.$to.'?&body='.rawurlencode($body);

        if ($engine === 'simulator') {
            $this->credits->logFree($agent, self::FEATURE, 'simulator');
        }

        return [
            'body' => self::line('assistant.draft.intro.'.$channel, ['name' => $contact->name()]), 'kind' => 'draft', 'items' => null, 'broker_question' => null, 'engine' => $engine,
            'draft' => ['channel' => $channel, 'to' => $to, 'name' => $contact->name(), 'subject' => $subject, 'body' => $body, 'href' => $href],
        ];
    }

    /**
     * A brokerage policy or Texas rule question: the virtual broker answers (and escalates when it should).
     *
     * @return Reply
     */
    private function policyReply(User $agent, string $question): array
    {
        try {
            $asked = $this->broker->ask($agent, $question, null);
        } catch (NotFoundHttpException) {
            return $this->generalReply($agent, $question);
        }

        $items = array_map(static fn (array $s): array => ['title' => $s['citation'] ?? ($s['page'] !== null ? self::line('virtualBroker.source.page', ['page' => (string) $s['page']]) : self::line('virtualBroker.source.'.($s['kind'] === 'faq' ? 'faq' : 'manual'))), 'detail' => Str::limit($s['excerpt'], 200), 'source' => $s['kind'], 'at' => $s['effective_from'], 'link' => $s['url']], $asked->sources);
        $body = $asked->status === 'waiting' ? $asked->answer."\n\n".self::line('assistant.policy.withBroker') : $asked->answer;

        return ['body' => $body, 'kind' => 'policy', 'items' => $items, 'draft' => null, 'broker_question' => $asked->uuid, 'engine' => $asked->engine];
    }

    /**
     * Anything else: Claude for credits; without keys, what the assistant can do.
     *
     * @return Reply
     */
    private function generalReply(User $agent, string $question): array
    {
        $cost = max(0, $this->settings->cost('general_answer'));
        $bedrock = $this->bedrock->client(30);

        if ($bedrock !== null) {
            if (! $this->credits->canSpend($agent, $cost)) {
                throw ValidationException::withMessages(['question' => self::line('assistant.errors.credits', ['credits' => (string) $cost])]);
            }

            try {
                $answer = (new ClaudeDrafter($bedrock['client'], $bedrock['model']))->general($question, $agent->locale->value);
                $this->credits->charge($agent, self::FEATURE, 'ai', $cost);

                return ['body' => $answer, 'kind' => 'general', 'items' => null, 'draft' => null, 'broker_question' => null, 'engine' => 'ai'];
            } catch (Throwable $e) {
                Log::warning('assistant.general_failed', ['error' => $e->getMessage()]);
            }
        }

        return ['body' => self::line('assistant.general.simulator'), 'kind' => 'none', 'items' => null, 'draft' => null, 'broker_question' => null, 'engine' => 'simulator'];
    }

    /** The agent's contact the question names ("text Ana Lopez", "email to María"). */
    private function namedContact(User $agent, string $question): ?Contact
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}\']+/u', $question) ?: [], static fn (string $w): bool => mb_strlen($w) >= 2 && preg_match('/^\p{Lu}/u', $w) === 1));

        foreach ($words as $word) {
            $found = $this->contacts($agent)->where(static fn ($q) => $q->where('first_name', $word)->orWhere('last_name', $word))->with('insight')->first();

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @return Builder<Contact> */
    private function contacts(User $agent): Builder
    {
        return Contact::query()->where('owner_type', 'user')->where('owner_id', $agent->id)->whereNull('merged_into_id');
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }
}
