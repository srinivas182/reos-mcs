<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A US state REOS serves (or will). Content, courses, plans and integrations
 * are tagged by state; A5.1 adds the full rules and licences.
 *
 * @property string $code
 * @property string $name
 * @property StateStatus $status
 * @property string|null $regulator
 * @property string|null $exam_vendor
 * @property int|null $qualifying_hours
 * @property int|null $ce_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class State extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'status', 'regulator', 'exam_vendor', 'qualifying_hours', 'ce_hours'];

    /** @return HasMany<WaitlistEntry, $this> */
    public function waitlist(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'state_code', 'code');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StateStatus::class,
            'qualifying_hours' => 'integer',
            'ce_hours' => 'integer',
        ];
    }
}
