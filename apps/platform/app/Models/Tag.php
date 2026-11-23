<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\HasPersonalRows;
use Illuminate\Database\Eloquent\Model;

/**
 * A contact tag (B7.1), per owner, the same names as the CRMs use.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 */
final class Tag extends Model implements HasPersonalRows
{
    use BelongsToTenant;

    protected $fillable = ['owner_type', 'owner_id', 'name'];
}
