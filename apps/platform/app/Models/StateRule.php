<?php

declare(strict_types=1);

namespace App\Models;

use App\StateRules\RuleTopic;
use App\StateRules\RuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a regulator rule (A5.2, ADR 0019; web journey screen 62).
 *
 * @property int $id
 * @property string $state_code
 * @property RuleTopic $topic
 * @property string $key
 * @property RuleType $type
 * @property array<string, mixed> $value
 * @property string $citation
 * @property string|null $source_url
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $checked_on
 * @property int $version
 * @property string $status `draft`, `published` or `retired`
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $publisher
 */
final class StateRule extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const RETIRED = 'retired';

    protected $fillable = [
        'state_code', 'topic', 'key', 'type', 'value', 'citation', 'source_url', 'effective_from', 'effective_to',
        'checked_on', 'version', 'status', 'note', 'created_by', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'topic' => RuleTopic::class,
            'type' => RuleType::class,
            'value' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'checked_on' => 'date',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
