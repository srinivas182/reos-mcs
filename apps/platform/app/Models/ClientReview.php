<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A client's review of their agent after closing (B14.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $client_portal_id
 * @property int $deal_id
 * @property int|null $organisation_id
 * @property int $user_id the agent
 * @property int $rating 1–5
 * @property string|null $comment
 * @property bool $publish shown on the agent's page
 * @property string $shown_name "Rosa L."
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ClientReview extends Model
{
    protected $fillable = ['uuid', 'client_portal_id', 'deal_id', 'organisation_id', 'user_id', 'rating', 'comment', 'publish', 'shown_name'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'publish' => 'boolean'];
    }
}
