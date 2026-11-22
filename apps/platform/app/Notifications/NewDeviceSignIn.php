<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Identity\DeviceInfo;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "You signed in on a new device", sent for every new phone or tablet. */
final class NewDeviceSignIn extends CentreNotification
{
    public function __construct(public readonly DeviceInfo $device, public readonly string $method) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewDevice;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('identity.newDevice.subject');
        $body = __('identity.newDevice.body', ['device' => $this->device->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.newDevice.subject'))
            ->line(__('identity.newDevice.body', ['device' => $this->device->name]))
            ->line(__('identity.newDevice.when', ['time' => now()->toDayDateTimeString()]))
            ->line(__('identity.newDevice.notYou'));
    }
}
