<?php

declare(strict_types=1);

namespace App\Content;

/** What a course is for (A5.3). CE and qualifying courses carry a regulator approval per state. */
enum CourseType: string
{
    case Qualifying = 'qualifying';
    case Ce = 'ce';
    case PostLicence = 'post_licence';
    case TestPrep = 'test_prep';
    case Elective = 'elective';

    /** No CE or qualifying label without a recorded approval (screen 63). */
    public function needsApproval(): bool
    {
        return $this === self::Qualifying || $this === self::Ce;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
