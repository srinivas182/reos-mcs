<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team split for one closing (A17.3), from the team agreement, signed by the agent.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property int|null $deal_id
 * @property int $agent_id
 * @property int|null $created_by
 * @property string $property
 * @property Carbon $closing_on
 * @property string|null $lead_source
 * @property int $gross_cents
 * @property int $brokerage_cents
 * @property int $team_cents
 * @property int $agent_cents
 * @property string $status sent, signed or questioned
 * @property Carbon|null $signed_at
 * @property Carbon|null $exported_at
 * @property string|null $export_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $agent
 * @property-read Organisation $organisation
 */
final class SplitSheet extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'deal_id', 'agent_id', 'created_by', 'property', 'closing_on', 'lead_source', 'gross_cents', 'brokerage_cents', 'team_cents', 'agent_cents', 'status', 'signed_at', 'exported_at', 'export_ref'];

    protected function casts(): array
    {
        return ['closing_on' => 'date', 'signed_at' => 'datetime', 'exported_at' => 'datetime', 'gross_cents' => 'integer', 'brokerage_cents' => 'integer', 'team_cents' => 'integer', 'agent_cents' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
