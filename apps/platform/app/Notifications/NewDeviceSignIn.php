<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Identity\DeviceInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "You signed in on a new device", sent for every new phone or tablet. */
final class NewDeviceSignIn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DeviceInfo $device, public readonly string $method) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
