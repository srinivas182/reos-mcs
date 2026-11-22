<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\Centre\Push\PushChannel;
use Carbon\CarbonImmutable;

/**
 * The notification centre (B6.2, ADR 0023). For each notification it picks
 * the channels from the person's choices for its kind: the inbox always
 * (silent), and alerts (push and email; SMS in B6.2c) unless they're
 * off. In quiet hours (default 9 PM to 7 AM in the person's time zone) an
 * alert that isn't urgent is held and sent when they end; a kind the person
 * gets as a digest is gathered instead.
 */
final class NotificationCentre
{
    /** Channel names Laravel sends through. */
    public const SEND_AS = [NotificationKind::INBOX => 'database', NotificationKind::PUSH => PushChannel::class, NotificationKind::EMAIL => 'mail'];

    /** @var array<int, NotificationSetting> */
    private array $settings = [];

    public function settings(User $user): NotificationSetting
    {
        return $this->settings[$user->id] ??= NotificationSetting::query()->firstOrNew(['user_id' => $user->id]);
    }

    public function forget(User $user): void
    {
        unset($this->settings[$user->id]);
    }

    /** Whether the person gets this kind on this channel. */
    public function wants(User $user, NotificationKind $kind, string $channel): bool
    {
        $default = in_array($channel, $kind->defaults(), true);

        if ($kind->locked()) {
            return $default;
        }

        $choice = $this->settings($user)->channels[$kind->value][$channel] ?? null;

        return is_bool($choice) ? $choice : $default;
    }

    public function digest(User $user, NotificationKind $kind): ?string
    {
        $frequency = $kind->digestible() ? ($this->settings($user)->digests[$kind->value] ?? null) : null;

        return in_array($frequency, ['daily', 'weekly'], true) ? $frequency : null;
    }

    /** @return list<string|class-string> */
    public function channels(User $user, CentreNotification $notification): array
    {
        $kind = $notification->kind();
        $send = [];
        $alerts = [];

        foreach (NotificationKind::CHANNELS as $channel) {
            if (! $this->wants($user, $kind, $channel)) {
                continue;
            }

            if ($channel === NotificationKind::INBOX) {
                // The inbox is silent: it never waits for the morning.
                $send[] = self::SEND_AS[$channel];
            } else {
                $alerts[] = $channel;
            }
        }

        if ($alerts === []) {
            return $send;
        }

        if ($this->digest($user, $kind) !== null) {
            return [...$send, DigestChannel::class];
        }

        if (! $notification->released && ! $kind->urgent() && $this->quiet($user)) {
            return [...$send, HeldChannel::class];
        }

        return [...$send, ...array_map(static fn (string $c): string => self::SEND_AS[$c], $alerts)];
    }

    /**
     * The alert channels a held notification goes out on when quiet hours end.
     *
     * @return list<string>
     */
    public function alertChannels(User $user, NotificationKind $kind): array
    {
        return array_values(array_map(
            static fn (string $c): string => self::SEND_AS[$c],
            array_filter(NotificationKind::CHANNELS, fn (string $c): bool => $c !== NotificationKind::INBOX && $this->wants($user, $kind, $c)),
        ));
    }

    public function quiet(User $user, ?CarbonImmutable $at = null): bool
    {
        $settings = $this->settings($user);

        if (! $settings->quiet_enabled) {
            return false;
        }

        $local = ($at ?? CarbonImmutable::now())->setTimezone($settings->timezone);
        $now = $local->format('H:i');
        [$start, $end] = [$settings->quiet_start, $settings->quiet_end];

        // 21:00–07:00 wraps past midnight; 13:00–15:00 doesn't.
        return $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    }

    /** When the current quiet hours end (the next quiet_end in the person's zone). */
    public function quietEnds(User $user, ?CarbonImmutable $at = null): CarbonImmutable
    {
        $settings = $this->settings($user);
        $local = ($at ?? CarbonImmutable::now())->setTimezone($settings->timezone);
        [$hour, $minute] = array_map(intval(...), explode(':', $settings->quiet_end));
        $end = $local->setTime($hour, $minute);

        return ($end->lessThanOrEqualTo($local) ? $end->addDay() : $end)->utc();
    }
}
