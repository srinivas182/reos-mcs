<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\CalendarEvent;
use App\Models\CeCompletion;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactNote;
use App\Models\SocialPost;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * An agent's own activity in a window, counted the same way everywhere:
 * the weekly scorecard (A11), pods and challenges (A13.2) and daily goals
 * and points (A13.3). Only the agent's own work counts; never anything
 * about choosing or recommending a service provider (RESPA).
 */
final class Activity
{
    public const METRICS = ['conversations', 'appointments', 'ce_hours', 'posts'];

    /** Calls, emails and texts logged with the agent's contacts, and notes they wrote. */
    public static function conversations(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $contacts = Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->select('id');

        return ContactActivity::query()->whereIn('contact_id', $contacts)->whereIn('kind', ['call', 'email', 'text'])->whereBetween('occurred_at', [$from->utc(), $to->utc()])->count()
            + ContactNote::query()->where('author_id', $user->id)->whereBetween('created_at', [$from->utc(), $to->utc()])->count();
    }

    public static function appointments(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return CalendarEvent::query()->where('user_id', $user->id)->whereIn('kind', ['appointment', 'showing'])->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])->count();
    }

    public static function ceHours(User $user, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) CeCompletion::query()->where('user_id', $user->id)->whereBetween('completed_on', [$from->toDateString(), $to->subSecond()->toDateString()])->sum('hours');
    }

    public static function posts(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return SocialPost::query()->where('user_id', $user->id)->whereNotNull('published_at')->whereBetween('published_at', [$from->utc(), $to->utc()])->count();
    }

    /** One metric's value in the window. */
    public static function value(string $metric, User $user, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return match ($metric) {
            'conversations' => self::conversations($user, $from, $to),
            'appointments' => self::appointments($user, $from, $to),
            'ce_hours' => self::ceHours($user, $from, $to),
            'posts' => self::posts($user, $from, $to),
            default => 0,
        };
    }
}
