<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An instructor's or mentor's 1-on-1 sessions (A10.2a): weekly hours,
 * prices and what they help with.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $bio
 * @property list<'en'|'es'> $languages
 * @property list<string> $topics
 * @property array<string, list<array{0: string, 1: string}>> $hours day (mon…sun) => [from, to] in `timezone`
 * @property string $timezone
 * @property int $price_30_cents
 * @property int $price_60_cents
 * @property int|null $product_30_id
 * @property int|null $product_60_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class SessionOffer extends Model
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const LENGTHS = [30, 60];

    /** @var list<string> */
    protected $fillable = ['user_id', 'bio', 'languages', 'topics', 'hours', 'timezone', 'price_30_cents', 'price_60_cents', 'product_30_id', 'product_60_id', 'active'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function price(int $minutes): int
    {
        return $minutes === 30 ? $this->price_30_cents : $this->price_60_cents;
    }

    public function productId(int $minutes): ?int
    {
        return $minutes === 30 ? $this->product_30_id : $this->product_60_id;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['languages' => 'array', 'topics' => 'array', 'hours' => 'array', 'active' => 'boolean'];
    }
}
