<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Centre\Sms\TextAlerts;
use Illuminate\Validation\ValidationException;

/**
 * Notification settings (B6.2; settings screen 23 and the phone's Me): per
 * kind and channel, quiet hours and digests. Security and account messages
 * can't be turned off.
 */
final readonly class Preferences
{
    public function __construct(private NotificationCentre $centre, private TextAlerts $texts) {}

    /**
     * @return array{kinds: list<array{kind: string, group: string, locked: bool, urgent: bool, digestible: bool, channels: array<string, bool>, digest: string|null}>, channels: list<string>, phones: int, texts: array{phone: string|null, on: bool, available: bool}, quiet: array{enabled: bool, start: string, end: string}, timezone: string}
     */
    public function summary(User $user): array
    {
        $settings = $this->centre->settings($user);

        return [
            'kinds' => array_map(fn (NotificationKind $kind): array => [
                'kind' => $kind->value,
                'group' => $kind->group(),
                'locked' => $kind->locked(),
                'urgent' => $kind->urgent(),
                'digestible' => $kind->digestible(),
                'channels' => array_combine(NotificationKind::CHANNELS, array_map(fn (string $c): bool => $this->centre->wants($user, $kind, $c), NotificationKind::CHANNELS)),
                'digest' => $this->centre->digest($user, $kind),
            ], NotificationKind::settable()),
            'channels' => NotificationKind::CHANNELS,
            // Phones signed in with push turned on.
            'phones' => PushDevice::query()->where('user_id', $user->id)->count(),
            // Texts (B6.2c): the verified number, masked, and whether they turned texts on.
            'texts' => $this->texts->status($user),
            'quiet' => ['enabled' => $settings->quiet_enabled, 'start' => $settings->quiet_start, 'end' => $settings->quiet_end],
            'timezone' => $settings->timezone,
        ];
    }

    /**
     * @param  array{channels?: array<string, array<string, bool>>, digests?: array<string, string|null>, quiet?: array{enabled: bool, start: string, end: string}, timezone?: string}  $changes
     */
    public function save(User $user, array $changes): void
    {
        $settings = $this->centre->settings($user);
        $channels = $settings->channels ?? [];
        $digests = $settings->digests ?? [];

        foreach ($changes['channels'] ?? [] as $kindValue => $choices) {
            $kind = NotificationKind::tryFrom($kindValue);

            if ($kind === null || $kind->locked()) {
                throw ValidationException::withMessages(['channels' => __('notifications.errors.locked')]);
            }

            foreach ($choices as $channel => $on) {
                if (in_array($channel, NotificationKind::CHANNELS, true)) {
                    $channels[$kind->value][$channel] = $on;
                }
            }
        }

        foreach ($changes['digests'] ?? [] as $kindValue => $frequency) {
            $kind = NotificationKind::tryFrom($kindValue);

            if ($kind === null || ! $kind->digestible()) {
                throw ValidationException::withMessages(['digests' => __('notifications.errors.noDigest')]);
            }

            if ($frequency === null) {
                unset($digests[$kind->value]);
            } else {
                $digests[$kind->value] = $frequency;
            }
        }

        $settings->fill(['channels' => $channels, 'digests' => $digests]);

        if (isset($changes['quiet'])) {
            $settings->fill(['quiet_enabled' => $changes['quiet']['enabled'], 'quiet_start' => $changes['quiet']['start'], 'quiet_end' => $changes['quiet']['end']]);
        }

        if (isset($changes['timezone'])) {
            $settings->timezone = $changes['timezone'];
        }

        $settings->save();
        $this->centre->forget($user);
    }
}
