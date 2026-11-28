<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Someone asked REOS for a provider category that isn't listed (B15.1):
 * once per person, with what they wrote.
 *
 * @property int $id
 * @property int $provider_category_id
 * @property int $user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ProviderCategoryRequest extends Model
{
    protected $fillable = ['provider_category_id', 'user_id', 'note'];
}
