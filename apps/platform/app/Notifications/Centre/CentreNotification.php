<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A notification that goes through REOS's notification centre (B6.2): the
 * centre picks its channels from the person's choices, holds alerts in
 * quiet hours and gathers digests. Each one says its kind and how it looks
 * in the inbox; email stays in `toMail`.
 */
abstract class CentreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Set when quiet hours end and a held alert is sent. */
    public bool $released = false;

    abstract public function kind(): NotificationKind;

    /**
     * The inbox line: a title, a short body and where it opens.
     *
     * @return array{title: string, body: string|null, url: string|null}
     */
    abstract public function inbox(object $notifiable): array;

    /**
     * The push to the phone (B6.2b): the inbox line, opening the screen in
     * the app that `appPath` names.
     *
     * @return array{title: string, body: string|null, path: string}
     */
    public function push(object $notifiable): array
    {
        $inbox = $this->inbox($notifiable);

        return ['title' => $inbox['title'], 'body' => $inbox['body'], 'path' => $this->appPath()];
    }

    /** Where a tap opens in the app; the notifications screen unless a kind has its own. */
    public function appPath(): string
    {
        return '/notifications';
    }

    /** Pushes with the same key replace each other on the phone (one per conversation). */
    public function collapseKey(): ?string
    {
        return null;
    }

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(NotificationCentre::class)->channels($notifiable, $this) : ['mail'];
    }

    /** @return array{kind: string, title: string, body: string|null, url: string|null} */
    public function toDatabase(object $notifiable): array
    {
        return ['kind' => $this->kind()->value, ...$this->inbox($notifiable)];
    }

    /** @return array{kind: string, title: string, body: string|null, url: string|null} */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
