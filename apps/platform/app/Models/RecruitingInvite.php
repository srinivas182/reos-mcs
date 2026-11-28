<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A brokerage's invitation to talk with a licensed agent open to a move (A17.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $brokerage_id
 * @property int $broker_id
 * @property int $agent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RecruitingInvite extends Model
{
    protected $fillable = ['uuid', 'brokerage_id', 'broker_id', 'agent_id'];
}
