<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Events\Messaging\AnnouncementPosted;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\NewAnnouncement;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Announcements (B6.1b): a brokerage, school or association admin writes
 * to all members or a group (roles, teams, states); each recipient sees it
 * once on Messages (web) and Today (phone), and the admin sees "Read by 82
 * of 140". Announcements are the tenant's (row-level security); people
 * read their own across tenants (a team agent gets the brokerage's).
 */
final readonly class Announcements
{
    /** Admin roles that can announce, and the organisation types they announce in. */
    public const SENDERS = [Role::Broker, Role::SchoolAdmin, Role::AssociationAdmin];

    public function __construct(
        private ActiveRole $roles,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** The admin membership announcing, or null when the active role can't announce. */
    public function sender(User $user): ?Membership
    {
        $membership = $this->roles->for($user);

        return $membership !== null && in_array($membership->role, self::SENDERS, true) && $membership->organisation->type !== OrganisationType::Team
            ? $membership
            : null;
    }

    /**
     * Who an announcement can go to: member roles, teams and states.
     *
     * @return array{roles: list<string>, teams: list<array{id: int, name: string}>, states: list<string>}
     */
    public function audienceOptions(Organisation $organisation): array
    {
        $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('model_has_roles.organisation_id', self::family($organisation))
            ->distinct()->orderBy('roles.name')->pluck('roles.name')
            ->filter(static fn (mixed $r): bool => is_string($r) && $r !== Role::SuperAdmin->value)
            ->values()->all();

        return [
            'roles' => array_values(array_filter($roles, is_string(...))),
            'teams' => array_values(Organisation::query()->where('parent_id', $organisation->id)->where('type', OrganisationType::Team)->orderBy('name')->get(['id', 'name'])
                ->map(static fn (Organisation $t): array => ['id' => $t->id, 'name' => $t->name])->all()),
            'states' => $organisation->states ?? [],
        ];
    }

    /**
     * Sends an announcement to everyone matching the audience (empty: everyone).
     *
     * @param  array{roles: list<string>, teams: list<int>, states: list<string>}  $audience
     */
    public function send(User $author, string $title, string $body, array $audience): Announcement
    {
        $sender = $this->sender($author);
        abort_if($sender === null, 403);
        $organisation = $sender->organisation;
        $recipients = $this->recipients($organisation, $audience, $author->id);

        if ($recipients === []) {
            throw ValidationException::withMessages(['audience' => __('announcements.errors.nobody')]);
        }

        $announcement = $this->tenancy->run($organisation->id, fn (): Announcement => DB::transaction(function () use ($author, $organisation, $title, $body, $audience, $recipients): Announcement {
            $announcement = Announcement::query()->create([
                'uuid' => (string) Str::uuid7(),
                'organisation_id' => $organisation->id,
                'author_id' => $author->id,
                'title' => Str::limit(trim($title), 120, ''),
                'body' => trim($body),
                'audience' => $audience,
                'recipients_count' => count($recipients),
                'sent_at' => now(),
            ]);

            foreach (array_chunk($recipients, 500) as $chunk) {
                AnnouncementRecipient::query()->insert(array_map(static fn (int $id): array => [
                    'announcement_id' => $announcement->id, 'organisation_id' => $organisation->id, 'user_id' => $id,
                ], $chunk));
            }

            return $announcement;
        }));

        $this->audit->record('announcement.sent', 'announcement', $announcement->uuid, ['title' => $announcement->title, 'recipients' => count($recipients), 'audience' => $audience], $author, $organisation->id);
        Realtime::send(new AnnouncementPosted($announcement, $recipients));
        // In each person's inbox, and by email or as a digest if they chose (B6.2).
        Notification::send(User::query()->whereIn('id', $recipients)->get(), new NewAnnouncement($announcement));

        return $announcement;
    }

    /**
     * What the admin's organisation has sent, newest first, with read counts.
     *
     * @return list<array{id: string, title: string, body: string, author: string|null, audience: array<string, mixed>, recipients: int, read: int, sent_at: string}>
     */
    public function sent(Organisation $organisation): array
    {
        return $this->tenancy->run($organisation->id, fn (): array => array_values(Announcement::query()->with('author')
            ->withCount(['recipients as read_count' => static fn ($q) => $q->whereNotNull('read_at')])
            ->where('organisation_id', $organisation->id)->latest('sent_at')->limit(50)->get()
            ->map(static fn (Announcement $a): array => [
                'id' => $a->uuid,
                'title' => $a->title,
                'body' => $a->body,
                'author' => $a->author?->name,
                'audience' => $a->audience,
                'recipients' => $a->recipients_count,
                'read' => is_numeric($a->read_count ?? null) ? (int) $a->read_count : 0,
                'sent_at' => $a->sent_at->toIso8601String(),
            ])->all()));
    }

    /**
     * The person's announcements from every organisation, newest first.
     *
     * @return list<array{id: string, title: string, body: string, from: string, author: string|null, sent_at: string, read: bool}>
     */
    public function mine(User $user, bool $unreadOnly = false): array
    {
        return $this->tenancy->asPlatform(fn (): array => array_values(AnnouncementRecipient::query()
            ->with(['announcement.organisation', 'announcement.author'])
            ->where('user_id', $user->id)
            ->when($unreadOnly, static fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('announcement_id')->limit(20)->get()
            ->map(static fn (AnnouncementRecipient $r): array => [
                'id' => $r->announcement->uuid,
                'title' => $r->announcement->title,
                'body' => $r->announcement->body,
                'from' => $r->announcement->organisation->name,
                'author' => $r->announcement->author?->name,
                'sent_at' => $r->announcement->sent_at->toIso8601String(),
                'read' => $r->read_at !== null,
            ])->all()));
    }

    public function read(User $user, string $uuid): void
    {
        $this->tenancy->asPlatform(function () use ($user, $uuid): void {
            $announcement = Announcement::query()->where('uuid', $uuid)->first();
            $recipient = $announcement === null ? null : AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->where('user_id', $user->id)->first();

            abort_if($recipient === null, 404);

            if ($recipient->read_at === null) {
                $recipient->forceFill(['read_at' => now()])->save();
            }
        });
    }

    /**
     * Members matching the audience: roles, teams (the brokerage's own people
     * stay unless teams narrow it) and the state they work in.
     *
     * @param  array{roles: list<string>, teams: list<int>, states: list<string>}  $audience
     * @return list<int>
     */
    private function recipients(Organisation $organisation, array $audience, int $author): array
    {
        $family = self::family($organisation);
        $organisations = $audience['teams'] === [] ? $family : array_values(array_intersect($family, $audience['teams']));

        $ids = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->whereIn('model_has_roles.organisation_id', $organisations === [] ? [0] : $organisations)
            ->where('roles.name', '!=', Role::SuperAdmin->value)
            ->when($audience['roles'] !== [], static fn ($q) => $q->whereIn('roles.name', $audience['roles']))
            ->when($audience['states'] !== [], static fn ($q) => $q->where(static fn ($w) => $w->whereIn('users.current_state', $audience['states'])
                ->orWhere(static fn ($x) => $x->whereNull('users.current_state')->whereIn('users.target_state', $audience['states']))))
            ->where('model_has_roles.model_id', '!=', $author)
            ->distinct()->pluck('model_has_roles.model_id');

        return array_values($ids->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->filter()->all());
    }

    /** @return list<int> the organisation and its teams */
    private static function family(Organisation $organisation): array
    {
        return array_values([$organisation->id, ...Organisation::query()->where('parent_id', $organisation->id)->pluck('id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all()]);
    }

    /**
     * Who has read an announcement and who hasn't (A16.3), for its organisation's admin.
     *
     * @return array{id: string, title: string, recipients: int, read: int, reminded_at: string|null, can_remind: bool, people: list<array{name: string, read_at: string|null}>}
     */
    public function readers(User $admin, string $uuid): array
    {
        $sender = $this->sender($admin);
        abort_if($sender === null, 403);
        $organisation = $sender->organisation;

        return $this->tenancy->run($organisation->id, static function () use ($organisation, $uuid): array {
            $announcement = Announcement::query()->where('organisation_id', $organisation->id)->where('uuid', $uuid)->firstOrFail();
            $people = AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->join('users', 'users.id', '=', 'announcement_recipients.user_id')
                ->orderByRaw('announcement_recipients.read_at is null desc')->orderBy('users.name')->limit(2000)
                ->get(['users.name', 'announcement_recipients.read_at']);
            $read = $people->whereNotNull('read_at')->count();

            return [
                'id' => $announcement->uuid, 'title' => $announcement->title, 'recipients' => $people->count(), 'read' => $read,
                'reminded_at' => $announcement->reminded_at?->toIso8601String(),
                'can_remind' => $read < $people->count() && ($announcement->reminded_at === null || $announcement->reminded_at->lt(now()->subDay())),
                'people' => array_values($people->map(static fn (AnnouncementRecipient $r): array => [
                    'name' => is_string($n = $r->getAttribute('name')) ? $n : '', 'read_at' => $r->read_at?->toIso8601String(),
                ])->all()),
            ];
        });
    }

    /**
     * Reminds everyone who hasn't read it yet, once a day at most (A16.3). Returns how many.
     */
    public function remindUnread(User $admin, string $uuid): int
    {
        $sender = $this->sender($admin);
        abort_if($sender === null, 403);
        $organisation = $sender->organisation;
        [$announcement, $unread] = $this->tenancy->run($organisation->id, static function () use ($organisation, $uuid): array {
            $announcement = Announcement::query()->where('organisation_id', $organisation->id)->where('uuid', $uuid)->firstOrFail();

            return [$announcement, AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->whereNull('read_at')->pluck('user_id')->all()];
        });

        if ($announcement->reminded_at !== null && $announcement->reminded_at->gt(now()->subDay())) {
            throw ValidationException::withMessages(['announcement' => __('announcements.receipts.errors.once')]);
        }

        if ($unread === []) {
            return 0;
        }

        Notification::send(User::query()->whereIn('id', $unread)->get(), new NewAnnouncement($announcement, true));
        $this->tenancy->run($organisation->id, static fn () => $announcement->forceFill(['reminded_at' => now()])->save());
        $this->audit->record('announcement.reminded', 'announcement', $announcement->uuid, ['unread' => count($unread)], $admin, $organisation->id);

        return count($unread);
    }
}
