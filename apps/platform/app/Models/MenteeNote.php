<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A mentor's private note on a mentee, seen by the mentor only (A13.1b, mentor screen 24).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property int $mentor_id
 * @property int $agent_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class MenteeNote extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'organisation_id', 'mentor_id', 'agent_id', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }
}
