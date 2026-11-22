<?php

declare(strict_types=1);

namespace App\Content;

/** Where a course is on its way to learners (A5.3; screen 63). Only Live courses are found by learners or search. */
enum CourseStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Live = 'live';
    case Hidden = 'hidden';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
