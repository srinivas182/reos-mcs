<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One math lab problem a candidate worked (A9.2c): per step, tries, hints
 * seen and whether it was right first time or shown.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property int $seed
 * @property array<int, array{tries: int, right: bool, shown: bool}> $steps
 * @property Carbon|null $solved_at
 */
final class MathSolve extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'kind', 'seed', 'steps', 'solved_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['seed' => 'integer', 'steps' => 'array', 'solved_at' => 'datetime'];
    }
}
