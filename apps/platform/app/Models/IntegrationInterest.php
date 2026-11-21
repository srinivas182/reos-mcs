<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A "Notify me" or "Join beta" tap.
 *
 * @property int $id
 * @property string $integration_id
 * @property int $user_id
 * @property string $kind
 * @property Carbon $created_at
 */
final class IntegrationInterest extends Model
{
    public const NOTIFY = 'notify';

    public const BETA = 'beta';

    /** "Ask my broker" (A4.3a): tools a brokerage connects once for everyone. */
    public const BROKER = 'broker';

    public const UPDATED_AT = null;

    protected $fillable = ['integration_id', 'user_id', 'kind'];
}
