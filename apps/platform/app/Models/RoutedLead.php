<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead the team's routing offered, assigned or reassigned (A17.3).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property string $source
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $language
 * @property string|null $message
 * @property string $status offered, claimed, assigned or leader
 * @property list<int>|null $offered_to
 * @property Carbon|null $claim_by
 * @property int|null $assigned_to
 * @property Carbon|null $assigned_at
 * @property string|null $how claim, round_robin, fallback or leader
 * @property string|null $auto_reply
 * @property string|null $contact_uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class RoutedLead extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'source', 'name', 'email', 'phone', 'language', 'message', 'status', 'offered_to', 'claim_by', 'assigned_to', 'assigned_at', 'how', 'auto_reply', 'contact_uuid'];

    protected function casts(): array
    {
        return ['offered_to' => 'array', 'claim_by' => 'datetime', 'assigned_at' => 'datetime'];
    }
}
