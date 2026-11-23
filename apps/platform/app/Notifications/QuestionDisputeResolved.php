<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QuestionDispute;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "The question you reported was corrected" (or kept, with why), to the learner who reported it (A8.1). */
final class QuestionDisputeResolved extends CentreNotification
{
    public function __construct(public readonly QuestionDispute $dispute) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ContentReview;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->dispute->resolution, 'url' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title())->line((string) $this->dispute->resolution);
    }

    private function title(): string
    {
        $text = __($this->dispute->status === 'corrected' ? 'review.mail.corrected' : 'review.mail.kept');

        return is_string($text) ? $text : '';
    }
}
