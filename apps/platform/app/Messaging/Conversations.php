<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\OrganisationType;
use App\Events\Messaging\ConversationRead;
use App\Events\Messaging\MessageChanged;
use App\Events\Messaging\MessagePosted;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Conversations and messages (B6.1, ADR 0023): start one (an existing
 * one-to-one is reused), send, reply, edit or delete for everyone within 5
 * minutes, and read. Only participants see a conversation; every change
 * goes out live through Reverb.
 */
final readonly class Conversations
{
    public const PAGE = 50;

    public function __construct(
        private Relationships $relationships,
        private Tenancy $tenancy,
        private Safety $safety,
        private ActiveRole $roles,
    ) {}

    /**
     * Starts a conversation with people the sender may message, with its first message.
     *
     * @param  list<int>  $userIds
     * @param  list<UploadedFile>  $files
     */
    public function start(User $from, array $userIds, string $body, ?string $title = null, array $files = []): Conversation
    {
        $others = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id !== $from->id)));

        if ($others === [] || count($others) > Conversation::MAX_PEOPLE - 1) {
            throw ValidationException::withMessages(['people' => __('messages.errors.people', ['max' => Conversation::MAX_PEOPLE - 1])]);
        }

        $allowed = array_map(static fn (array $c): int => $c['user']->id, $this->relationships->contacts($from));

        if (array_diff($others, $allowed) !== []) {
            throw ValidationException::withMessages(['people' => __('messages.errors.notAllowed')]);
        }

        if (count($others) === 1 && ($existing = $this->direct($from->id, $others[0])) !== null) {
            $this->post($from, $existing, $body, null, $files);

            return $existing;
        }

        $conversation = DB::transaction(function () use ($from, $others, $title): Conversation {
            $conversation = Conversation::query()->create([
                'uuid' => (string) Str::uuid7(),
                'organisation_id' => $this->tenancy->tenant() ?? $this->homeOrganisation($from),
                'kind' => count($others) === 1 ? Conversation::DIRECT : Conversation::GROUP,
                'title' => count($others) === 1 || $title === null || trim($title) === '' ? null : Str::limit(trim($title), 120, ''),
                'created_by' => $from->id,
            ]);

            foreach ([$from->id, ...$others] as $id) {
                ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $id, 'joined_at' => now()]);
            }

            return $conversation;
        });

        $this->post($from, $conversation, $body, null, $files);

        return $conversation;
    }

    /** @param  list<UploadedFile>  $files  images and PDFs (B6.1b) */
    public function post(User $from, Conversation $conversation, string $body, ?string $replyTo = null, array $files = []): Message
    {
        $this->participant($from, $conversation);
        $body = trim($body);

        if (count($files) > MessageAttachment::MAX_FILES) {
            throw ValidationException::withMessages(['files' => __('messages.errors.files', ['max' => MessageAttachment::MAX_FILES])]);
        }

        // A message is words, files or both.
        if (($body === '' && $files === []) || mb_strlen($body) > Message::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => __('messages.errors.body', ['max' => Message::MAX_LENGTH])]);
        }

        // A one-to-one needs the relationship still in place (an agent who left the brokerage can't be messaged).
        if ($conversation->kind === Conversation::DIRECT) {
            $other = $this->others($conversation, $from)[0] ?? null;

            // Blocked either way: no more one-to-one messages (B6.1b).
            if ($other !== null && $this->safety->between($from->id, $other->id)) {
                throw ValidationException::withMessages(['body' => __('messages.errors.blocked')]);
            }

            if ($other === null || ! $this->relationships->canMessage($from, $other)) {
                throw ValidationException::withMessages(['body' => __('messages.errors.notAllowed')]);
            }
        }

        $reply = $replyTo === null ? null : Message::query()->where('conversation_id', $conversation->id)->where('uuid', $replyTo)->first();

        $message = DB::transaction(function () use ($from, $conversation, $body, $reply, $files): Message {
            $message = Message::query()->create([
                'uuid' => (string) Str::uuid7(),
                'conversation_id' => $conversation->id,
                'user_id' => $from->id,
                'body' => $body === '' ? null : $body,
                'reply_to_id' => $reply?->id,
                'created_at' => now(),
            ]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            // Sending reads everything before it.
            ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $from->id)
                ->update(['last_read_message_id' => $message->id, 'last_read_at' => $message->created_at]);

            if ($files !== []) {
                $message->setRelation('conversation', $conversation);
                Attachments::store($message, $files);
            }

            return $message;
        });

        $message->setRelation('conversation', $conversation);
        $message->load(['attachments', 'sender', 'replyTo.sender']);
        Realtime::send(new MessagePosted($message, $this->participantIds($conversation)));
        $this->notify($from, $conversation, $message);

        return $message;
    }

    /**
     * Push to everyone else's phone (B6.2b); the web shows it live. Not to
     * people who blocked the sender.
     */
    private function notify(User $from, Conversation $conversation, Message $message): void
    {
        $blocked = $this->safety->blockedWith($from->id);
        $recipients = array_values(array_filter($this->others($conversation, $from), static fn (User $u): bool => ! in_array($u->id, $blocked, true)));

        if ($recipients !== []) {
            Notification::send($recipients, new NewMessage($message));
        }
    }

    public function edit(User $by, Message $message, string $body): Message
    {
        $this->mayChange($by, $message);
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > Message::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => __('messages.errors.body', ['max' => Message::MAX_LENGTH])]);
        }

        if ($body !== $message->body) {
            $message->forceFill(['body' => $body, 'edited_at' => now()])->save();
            Realtime::send(new MessageChanged($message));
        }

        return $message;
    }

    /** Deleted for everyone: the words go; "This message was deleted" stays in its place. */
    public function delete(User $by, Message $message): Message
    {
        $this->mayChange($by, $message);
        $message->forceFill(['body' => null, 'deleted_at' => now()])->save();
        // Its files go too.
        Attachments::forget($message);
        $message->setRelation('attachments', new Collection);
        Realtime::send(new MessageChanged($message));

        return $message;
    }

    /** Read up to the latest message. */
    public function read(User $user, Conversation $conversation): void
    {
        $participant = $this->participant($user, $conversation);
        $latest = Message::query()->where('conversation_id', $conversation->id)->max('id');

        if (! is_numeric($latest) || (int) $latest === $participant->last_read_message_id) {
            return;
        }

        $participant->forceFill(['last_read_message_id' => (int) $latest, 'last_read_at' => now()])->save();
        $participant->setRelation('conversation', $conversation);
        Realtime::send(new ConversationRead($participant));
    }

    /**
     * The person's conversations, latest first, with unread counts.
     *
     * @return list<array{id: string, kind: string, title: string, people: list<array{id: int, name: string}>, last_message: array{body: string|null, sender: string|null, deleted: bool, files: int, at: string}|null, unread: int, blocked: string|null, updated_at: string|null}>
     */
    public function inbox(User $user): array
    {
        $participants = ConversationParticipant::query()
            ->with(['conversation.participants.user'])
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->get();

        $rows = [];

        foreach ($participants as $participant) {
            $conversation = $participant->conversation;
            $last = Message::query()->with('sender')->withCount('attachments')->where('conversation_id', $conversation->id)->latest('id')->first();
            $other = $conversation->kind === Conversation::DIRECT
                ? $conversation->participants->first(static fn (ConversationParticipant $p): bool => $p->user_id !== $user->id)
                : null;
            $unread = Message::query()->where('conversation_id', $conversation->id)
                ->where('id', '>', $participant->last_read_message_id ?? 0)
                ->where(static fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
                ->whereNull('deleted_at')
                ->count();

            $rows[] = [
                'id' => $conversation->uuid,
                'kind' => $conversation->kind,
                'title' => $this->title($conversation, $user),
                'people' => $this->people($conversation, $user),
                'last_message' => $last === null ? null : [
                    'body' => $last->deleted_at === null && $last->body !== null ? Str::limit($last->body, 140) : null,
                    'sender' => $last->sender?->name,
                    'deleted' => $last->deleted_at !== null,
                    'files' => is_numeric($last->attachments_count ?? null) ? (int) $last->attachments_count : 0,
                    'at' => $last->created_at->toIso8601String(),
                ],
                'unread' => $unread,
                'blocked' => $this->blockState($user, $other?->user_id),
                'updated_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

        return $rows;
    }

    /** Unread messages across every conversation (the Messages badge). */
    public function unread(User $user): int
    {
        return array_sum(array_column($this->inbox($user), 'unread'));
    }

    /**
     * One conversation: its people, how far each has read, and a page of
     * messages (newest last), older pages before a message id.
     *
     * @return array{id: string, kind: string, title: string, people: list<array{id: int, name: string}>, reads: list<array{user: int, read_at: string|null}>, messages: list<array<string, mixed>>, older: bool, blocked: string|null}
     */
    public function show(User $user, Conversation $conversation, ?string $before = null): array
    {
        $this->participant($user, $conversation);
        $conversation->loadMissing('participants.user');
        $beforeId = $before === null ? null : Message::query()->where('conversation_id', $conversation->id)->where('uuid', $before)->value('id');

        $page = Message::query()
            ->with(['sender', 'replyTo.sender', 'attachments'])
            ->where('conversation_id', $conversation->id)
            ->when(is_numeric($beforeId), static fn ($q) => $q->where('id', '<', $beforeId))
            ->latest('id')
            ->limit(self::PAGE + 1)
            ->get();

        $messages = $page->take(self::PAGE)->reverse()->values();
        // People who blocked the viewer don't share their read state with them (B6.1b).
        $hidden = $this->safety->blockedWith($user->id);
        $other = $conversation->kind === Conversation::DIRECT
            ? $conversation->participants->first(static fn (ConversationParticipant $p): bool => $p->user_id !== $user->id)
            : null;

        return [
            'id' => $conversation->uuid,
            'kind' => $conversation->kind,
            'title' => $this->title($conversation, $user),
            'people' => $this->people($conversation, $user),
            'reads' => array_values($conversation->participants
                ->filter(static fn (ConversationParticipant $p): bool => $p->user_id !== $user->id && $p->left_at === null && ! in_array($p->user_id, $hidden, true))
                ->map(static fn (ConversationParticipant $p): array => ['user' => $p->user_id, 'read_at' => $p->last_read_at?->toIso8601String()])
                ->all()),
            'messages' => array_values($messages->map(static fn (Message $m): array => MessagePresenter::message($m))->all()),
            'older' => $page->count() > self::PAGE,
            'blocked' => $this->blockState($user, $other?->user_id),
        ];
    }

    /** Where the sender works (outside a request's tenant scope, such as jobs and seeding). */
    private function homeOrganisation(User $user): ?int
    {
        $membership = $this->roles->for($user);

        return $membership !== null && $membership->organisation->type !== OrganisationType::Platform ? $membership->organisation->id : null;
    }

    /** `by_me`, `by_them` or null, for a one-to-one. */
    private function blockState(User $viewer, ?int $other): ?string
    {
        return match (true) {
            $other === null => null,
            $this->safety->blocked($viewer->id, $other) => 'by_me',
            $this->safety->blocked($other, $viewer->id) => 'by_them',
            default => null,
        };
    }

    public function isParticipant(User $user, Conversation $conversation): bool
    {
        return ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $user->id)->whereNull('left_at')->exists();
    }

    private function participant(User $user, Conversation $conversation): ConversationParticipant
    {
        $participant = ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $user->id)->whereNull('left_at')->first();

        // Not theirs: as if it didn't exist.
        abort_if($participant === null, 404);

        return $participant;
    }

    private function mayChange(User $by, Message $message): void
    {
        abort_unless($message->user_id === $by->id, 403);

        if (! $message->changeable()) {
            throw ValidationException::withMessages(['body' => __('messages.errors.tooLate', ['minutes' => Message::CHANGE_MINUTES])]);
        }
    }

    private function direct(int $a, int $b): ?Conversation
    {
        return Conversation::query()
            ->where('kind', Conversation::DIRECT)
            ->whereHas('participants', static fn ($q) => $q->where('user_id', $a)->whereNull('left_at'))
            ->whereHas('participants', static fn ($q) => $q->where('user_id', $b)->whereNull('left_at'))
            ->first();
    }

    /** @return list<User> */
    private function others(Conversation $conversation, User $user): array
    {
        return array_values(User::query()->whereIn('id', ConversationParticipant::query()->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)->whereNull('left_at')->pluck('user_id'))->get()->all());
    }

    /** @return list<int> */
    private function participantIds(Conversation $conversation): array
    {
        return array_values(ConversationParticipant::query()->where('conversation_id', $conversation->id)->whereNull('left_at')
            ->pluck('user_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all());
    }

    /** @return list<array{id: int, name: string}> */
    private function people(Conversation $conversation, User $viewer): array
    {
        return array_values($conversation->participants
            ->filter(static fn (ConversationParticipant $p): bool => $p->user_id !== $viewer->id && $p->left_at === null)
            ->map(static fn (ConversationParticipant $p): array => ['id' => $p->user_id, 'name' => $p->user->name ?? $p->user->email ?? ''])
            ->all());
    }

    private function title(Conversation $conversation, User $viewer): string
    {
        return $conversation->title ?? implode(', ', array_column($this->people($conversation, $viewer), 'name'));
    }
}
