<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource the owner shares with the community (B17.2): a title and a link.
 *
 * @property int $id
 * @property string $uuid
 * @property int $community_id
 * @property string $title
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CommunityResource extends Model
{
    protected $fillable = ['uuid', 'community_id', 'title', 'url'];
}
