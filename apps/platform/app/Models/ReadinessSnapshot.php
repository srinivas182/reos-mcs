<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One candidate's readiness on one day (A9.2, ADR 0035), kept nightly and
 * after every test.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $day
 * @property int|null $national Predicted pass chance, National portion
 * @property int|null $state Predicted pass chance, state portion
 * @property int|null $percent Readiness: the lower of the two
 * @property array<string, int|null> $topics Share right per topic
 * @property list<string> $weakest
 * @property bool $ready_to_book
 */
final class ReadinessSnapshot extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'day', 'national', 'state', 'percent', 'topics', 'weakest', 'ready_to_book'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'national' => 'integer',
            'state' => 'integer',
            'percent' => 'integer',
            'topics' => 'array',
            'weakest' => 'array',
            'ready_to_book' => 'boolean',
        ];
    }
}
