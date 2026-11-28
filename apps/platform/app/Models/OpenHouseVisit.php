<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One visitor's sign-in at an open house (B14.3a).
 *
 * @property int $id
 * @property string $uuid
 * @property int $open_house_id
 * @property int|null $organisation_id
 * @property string|null $client_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $has_agent
 * @property bool $pre_approved
 * @property string|null $timeline now, 3_months, 6_months, later or just_looking
 * @property bool $consent
 * @property string $source qr or kiosk
 * @property int|null $contact_id
 * @property CarbonImmutable $signed_in_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class OpenHouseVisit extends Model
{
    protected $fillable = ['uuid', 'open_house_id', 'organisation_id', 'client_id', 'name', 'email', 'phone', 'has_agent', 'pre_approved', 'timeline', 'consent', 'source', 'contact_id', 'signed_in_at'];

    protected function casts(): array
    {
        return ['has_agent' => 'boolean', 'pre_approved' => 'boolean', 'consent' => 'boolean', 'signed_in_at' => 'immutable_datetime'];
    }
}
