<?php

declare(strict_types=1);

namespace App\Content;

/**
 * How a course is delivered (A7.1). A state approval covers one delivery
 * method: an approval for classroom delivery doesn't make the same course
 * creditable as an AI avatar course (Texas: 22 TAC §535.62, §535.72).
 */
enum Delivery: string
{
    case Classroom = 'classroom';
    case LiveOnline = 'live_online';
    case SelfPaced = 'self_paced';
    case AiAvatar = 'ai_avatar';

    /** Live delivery: attendance rules apply (present for the hours credited). */
    public function live(): bool
    {
        return $this === self::Classroom || $this === self::LiveOnline;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $d): string => $d->value, self::cases());
    }
}
