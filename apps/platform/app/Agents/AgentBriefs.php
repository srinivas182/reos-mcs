<?php

declare(strict_types=1);

namespace App\Agents;

use App\Authorization\Role;
use App\Crm\Segments;
use App\Models\AgentProfile;
use App\Models\Contact;
use App\Models\Licence;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\MorningBriefReady;
use App\Services\Authorization\ActiveRole;
use App\Services\StateRules\CeRequirements;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;

/**
 * The morning brief (A11.1c, ADR 0044; agent screen 184): a spoken summary
 * of the day of about 60 seconds, with the same words as a transcript on the
 * web. It covers to-dos, appointments, deal deadlines, new leads and CE, then
 * names the person to call first and offers a few commands ("Call Ana",
 * "Open the Oak Ridge deal"). It goes out at the agent's chosen time in
 * their own time zone; the notification only says it's ready, so nothing
 * private shows on a lock screen.
 *
 * @phpstan-type Settings array{enabled: bool, at: string, timezone: string, sections: list<string>}
 * @phpstan-type Command array{kind: 'call'|'deal'|'today', label: string, phone: string|null, contact: string|null, deal: string|null}
 * @phpstan-type Brief array{date: string, sections: list<array{key: string, count: int, text: string}>, first_call: array{name: string, phone: string, contact: string}|null, commands: list<Command>, script: list<string>}
 */
final readonly class AgentBriefs
{
    public const SECTIONS = ['todos', 'appointments', 'deadlines', 'leads', 'ce'];

    /** The brief goes out up to two hours after its time, never later in the day. */
    public const WINDOW_MINUTES = 120;

    public function __construct(
        private AgentModes $modes,
        private AgentToday $today,
        private CeRequirements $requirements,
        private PersonStates $states,
        private ActiveRole $roles,
    ) {}

    /** @return Settings */
    public function settings(User $user): array
    {
        $this->modes->show($user);
        $profile = $this->profile($user);

        return [
            'enabled' => $profile->brief_enabled,
            'at' => $profile->brief_at,
            'timezone' => $profile->brief_timezone,
            'sections' => $this->sections($profile),
        ];
    }

    /**
     * @param  list<string>  $sections
     * @return Settings
     */
    public function save(User $user, bool $enabled, string $at, string $timezone, array $sections): array
    {
        $this->modes->show($user);
        $chosen = array_values(array_intersect(self::SECTIONS, $sections));
        $this->profile($user)->forceFill([
            'brief_enabled' => $enabled, 'brief_at' => $at, 'brief_timezone' => $timezone,
            'brief_sections' => $chosen === self::SECTIONS ? null : $chosen,
        ])->save();

        return $this->settings($user);
    }

    /** @return Brief */
    public function for(User $user, ?CarbonImmutable $now = null): array
    {
        $this->modes->show($user);
        $profile = $this->profile($user);
        $now = ($now ?? CarbonImmutable::now())->setTimezone($profile->brief_timezone);
        $from = $now->startOfDay();
        $items = $this->today->ranked($user, $from, $from->addDay());
        $chosen = $this->sections($profile);
        $sections = [];

        $pick = static fn (array $reasons): array => array_values(array_filter($items, static fn (array $i): bool => in_array($i['reason'], $reasons, true)));
        $todos = $pick(['overdue', 'follow_up']);
        $appointments = $pick(['appointment']);
        $deadlines = $pick(['deadline']);
        $leads = $this->newLeads($user, $now);

        foreach ($chosen as $key) {
            $section = match ($key) {
                'todos' => $this->section('todos', count($todos), ['first' => $todos[0]['title'] ?? ''], count(array_filter($todos, static fn (array $i): bool => $i['reason'] === 'overdue'))),
                'appointments' => $this->section('appointments', count($appointments), ['first' => $appointments[0]['title'] ?? '', 'time' => isset($appointments[0]['at']) ? $this->time($appointments[0]['at'], $profile->brief_timezone) : '']),
                'deadlines' => $this->section('deadlines', count($deadlines), ['first' => trim(($deadlines[0]['title'] ?? '').(isset($deadlines[0]['detail']) ? ', '.$deadlines[0]['detail'] : ''))]),
                'leads' => $this->section('leads', count($leads), ['first' => isset($leads[0]) ? $leads[0]->name() : '']),
                default => $this->ce($user, $now),
            };

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $first = $this->firstCall($leads, $todos, $user);
        $commands = [];

        if ($first !== null) {
            $commands[] = ['kind' => 'call', 'label' => self::line('agents.brief.commands.call', ['name' => $first['name']]), 'phone' => $first['phone'], 'contact' => $first['contact'], 'deal' => null];
        }

        foreach ($deadlines as $deadline) {
            if ($deadline['deal'] !== null && $deadline['detail'] !== null) {
                $commands[] = ['kind' => 'deal', 'label' => self::line('agents.brief.commands.deal', ['property' => $deadline['detail']]), 'phone' => null, 'contact' => null, 'deal' => $deadline['deal']];
                break;
            }
        }

        $commands[] = ['kind' => 'today', 'label' => self::line('agents.brief.commands.today'), 'phone' => null, 'contact' => null, 'deal' => null];
        $name = explode(' ', trim($user->name ?? ''))[0];
        $script = [
            $name === '' ? self::line('agents.brief.greetingNoName') : self::line('agents.brief.greeting', ['name' => $name]),
            ...array_column($sections, 'text'),
            ...($first === null ? [] : [self::line('agents.brief.firstCall', ['name' => $first['name']])]),
            self::line('agents.brief.outro'),
        ];

        return ['date' => $from->toDateString(), 'sections' => $sections, 'first_call' => $first, 'commands' => $commands, 'script' => $script];
    }

    /**
     * Send each agent's brief notification once a day, at their time.
     *
     * @return int how many went out
     */
    public function sendDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $sent = 0;

        AgentProfile::query()->where('brief_enabled', true)->whereNotNull('goal')->with('user')->chunkById(200, function ($profiles) use ($now, &$sent): void {
            foreach ($profiles as $profile) {
                $local = $now->setTimezone($profile->brief_timezone);
                [$hour, $minute] = array_map('intval', explode(':', $profile->brief_at) + [0, 0]);
                $due = $local->startOfDay()->setTime($hour, $minute);
                $user = $profile->user;

                if ($user === null || $local->lt($due) || $local->gt($due->addMinutes(self::WINDOW_MINUTES))
                    || $profile->brief_sent_on?->toDateString() === $local->toDateString()
                    || $this->roles->for($user)?->role !== Role::Agent) {
                    continue;
                }

                $profile->forceFill(['brief_sent_on' => $local->toDateString()])->save();
                $user->notify(new MorningBriefReady);
                $sent++;
            }
        });

        return $sent;
    }

    /**
     * @param  array<string, string>  $values
     * @return array{key: string, count: int, text: string}
     */
    private function section(string $key, int $count, array $values, int $overdue = 0): array
    {
        $text = self::choice("agents.brief.sections.{$key}", $count, $values);

        if ($overdue > 0) {
            $text .= ' '.self::choice('agents.brief.sections.overdue', $overdue);
        }

        return ['key' => $key, 'count' => $count, 'text' => $text];
    }

    /** @return array{key: string, count: int, text: string}|null */
    private function ce(User $user, CarbonImmutable $now): ?array
    {
        $licence = Licence::query()->where('user_id', $user->id)->where('state_code', $this->states->current($user))->first();
        $ce = $licence === null || ! $licence->status->counts() ? null : $this->requirements->for($licence, $now->startOfDay());

        if ($ce === null) {
            return null;
        }

        $text = $ce['complete'] ? self::line('agents.brief.sections.ceComplete') : self::line('agents.brief.sections.ce', [
            'done' => $this->number($ce['hours_done']), 'required' => $this->number($ce['hours_required']),
            'date' => $ce['due_on'] === null ? '' : self::localised(CarbonImmutable::parse($ce['due_on']))->isoFormat('LL'),
        ]);

        return ['key' => 'ce', 'count' => (int) ceil($ce['hours_left']), 'text' => $text];
    }

    /** @return list<Contact> New leads since this time yesterday, newest first. */
    private function newLeads(User $user, CarbonImmutable $now): array
    {
        $query = Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->where('created_at', '>=', $now->subDay()->utc());

        return array_values(Segments::apply($query, 'new')->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()->all());
    }

    /**
     * The newest lead with a phone, or else the contact on the first follow-up.
     *
     * @param  list<Contact>  $leads
     * @param  list<array{reason: string, todo_id: int|null}>  $todos
     * @return array{name: string, phone: string, contact: string}|null
     */
    private function firstCall(array $leads, array $todos, User $user): ?array
    {
        $candidates = $leads;
        $ids = array_values(array_filter(array_column($todos, 'todo_id'), 'is_int'));

        if ($ids !== []) {
            $uuids = Todo::query()->whereIn('id', $ids)->where('related_type', 'contact')->pluck('related_id')->filter()->all();
            $candidates = [...$candidates, ...Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->whereIn('uuid', $uuids)->get()->all()];
        }

        foreach ($candidates as $contact) {
            $phone = $contact->phones[0]['value'] ?? null;

            if (is_string($phone) && $phone !== '' && $contact->name() !== '') {
                return ['name' => $contact->name(), 'phone' => $phone, 'contact' => $contact->uuid];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function sections(AgentProfile $profile): array
    {
        return $profile->brief_sections ?? self::SECTIONS;
    }

    private function profile(User $user): AgentProfile
    {
        return AgentProfile::query()->firstOrCreate(['user_id' => $user->id]);
    }

    private function time(string $iso, string $zone): string
    {
        return self::localised(CarbonImmutable::parse($iso)->setTimezone($zone))->isoFormat('LT');
    }

    private static function localised(CarbonImmutable $date): CarbonImmutable
    {
        $local = $date->locale(app()->getLocale());

        return $local instanceof CarbonImmutable ? $local : $date;
    }

    private function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }

    /** @param array<string, string> $replace */
    private static function line(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }

    /** @param array<string, string> $replace */
    private static function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice($key, $count, ['count' => (string) $count, ...$replace]);
    }
}
