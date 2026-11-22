<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Support\Str;

/**
 * A message for someone (B6.2b): a push to their phone (the web shows it
 * live), one per conversation however many arrive. Not sent if they've
 * read it by then (a held one in the morning, say) or it was deleted.
 */
final class NewMessage extends CentreNotification
{
    public function __construct(public readonly Message $message) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Message;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender->name ?? __('notifications.push.someone');
        $title = $conversation->kind === Conversation::GROUP && $conversation->title !== null
            ? __('notifications.push.inGroup', ['name' => $sender, 'group' => $conversation->title])
            : $sender;

        return ['title' => $title, 'body' => $this->preview(), 'url' => url('/messages/'.$conversation->uuid)];
    }

    /** @return array{title: string, body: string|null, path: string} */
    public function push(object $notifiable): array
    {
        $push = parent::push($notifiable);
        $unread = $this->unread($notifiable);

        if ($unread > 1) {
            $push['title'] = __('notifications.push.more', ['title' => $push['title'], 'count' => $unread]);
        }

        return $push;
    }

    public function appPath(): string
    {
        return '/messages/'.$this->message->conversation->uuid;
    }

    public function collapseKey(): string
    {
        return 'conversation-'.$this->message->conversation->uuid;
    }

    /** Laravel asks before each channel: still unread, still in the conversation, not deleted. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $participant = $this->participant($notifiable);

        return $this->message->deleted_at === null && $participant !== null
            && ($participant->last_read_message_id ?? 0) < $this->message->id;
    }

    private function preview(): string
    {
        if ($this->message->body !== null) {
            return Str::limit($this->message->body, 160);
        }

        $files = $this->message->attachments()->count();

        return __('notifications.push.files_'.($files === 1 ? 'one' : 'other'), ['count' => $files]);
    }

    /** Messages from others the person hasn't read in this conversation. */
    private function unread(object $notifiable): int
    {
        $participant = $this->participant($notifiable);

        if ($participant === null) {
            return 0;
        }

        return Message::query()->where('conversation_id', $this->message->conversation_id)
            ->where('id', '>', $participant->last_read_message_id ?? 0)
            ->where('user_id', '!=', $participant->user_id)
            ->whereNull('deleted_at')
            ->count();
    }

    private function participant(object $notifiable): ?ConversationParticipant
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        return ConversationParticipant::query()->where('conversation_id', $this->message->conversation_id)
            ->where('user_id', $notifiable->id)->whereNull('left_at')->first();
    }
}
