<?php

declare(strict_types=1);

namespace App\Crm;

use Illuminate\Database\Eloquent\Builder;

/**
 * The CRM hub's tabs (web screen 13, B8.3): every CRM names its stages its
 * own way ("Lead", "Open - Not Contacted", "Active buyer", "Past client"),
 * so a contact's segment is read from the words in its stage. The first
 * match wins, in this order: past clients, sphere, new leads, active. A
 * contact with no stage counts as a new lead. The same rule runs in PHP
 * (`of`) and in SQL (`apply`).
 */
final class Segments
{
    public const ALL = 'all';

    /** @var array<string, list<string>> Segment => words in the stage, lower case. */
    public const WORDS = [
        'past' => ['past', 'closed', 'sold'],
        'sphere' => ['sphere', 'friend', 'family'],
        'new' => ['new', 'lead', 'not contacted', 'uncontacted', 'open'],
        'active' => ['active', 'client', 'buyer', 'seller', 'hot', 'prospect', 'pending', 'contract', 'nurture', 'working', 'qualified', 'showing'],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return [self::ALL, 'new', 'active', 'past', 'sphere'];
    }

    /** A contact's segment, or null when the stage says nothing we know (still in All). */
    public static function of(?string $stage): ?string
    {
        $stage = mb_strtolower(trim($stage ?? ''));

        if ($stage === '') {
            return 'new';
        }

        foreach (self::WORDS as $segment => $words) {
            foreach ($words as $word) {
                if (str_contains($stage, $word)) {
                    return $segment;
                }
            }
        }

        return null;
    }

    /**
     * Only the contacts in a segment ("all" is every contact).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $segment): Builder
    {
        if (! array_key_exists($segment, self::WORDS)) {
            return $query;
        }

        $earlier = [];
        foreach (array_keys(self::WORDS) as $name) {
            if ($name === $segment) {
                break;
            }
            $earlier = [...$earlier, ...self::WORDS[$name]];
        }

        $words = self::WORDS[$segment];
        $matching = static function (Builder $q, array $list): void {
            foreach ($list as $word) {
                $q->orWhereRaw("LOWER(COALESCE(stage, '')) LIKE ?", ['%'.$word.'%']);
            }
        };

        return $query->where(static function (Builder $q) use ($segment, $words, $earlier, $matching): void {
            $q->where(static function (Builder $q) use ($words, $earlier, $matching): void {
                $q->where(static fn (Builder $q) => $matching($q, $words));
                if ($earlier !== []) {
                    $q->whereNot(static fn (Builder $q) => $matching($q, $earlier));
                }
            });

            if ($segment === 'new') {
                $q->orWhereRaw("COALESCE(TRIM(stage), '') = ''");
            }
        });
    }
}
