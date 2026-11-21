<?php

declare(strict_types=1);

namespace App\Models;

use App\Catalogue\CatalogueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The super admin's catalogue choices for one tool (B3.2). A tool without a
 * row uses the register's defaults.
 *
 * @property string $integration_id
 * @property CatalogueStatus|null $status
 * @property Carbon|null $retires_on
 * @property list<string>|null $states
 * @property list<string>|null $roles
 * @property list<string>|null $plans
 * @property list<int> $beta_organisations
 * @property bool $tenants_may_hide
 * @property bool $tenants_may_require
 * @property bool $show_coming_soon
 * @property int|null $updated_by
 */
final class CatalogueSetting extends Model
{
    protected $primaryKey = 'integration_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['integration_id', 'status', 'retires_on', 'states', 'roles', 'plans', 'beta_organisations', 'tenants_may_hide', 'tenants_may_require', 'show_coming_soon', 'updated_by'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'beta_organisations' => '[]',
        'tenants_may_hide' => true,
        'tenants_may_require' => false,
        'show_coming_soon' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CatalogueStatus::class,
            'retires_on' => 'date',
            'states' => 'array',
            'roles' => 'array',
            'plans' => 'array',
            'beta_organisations' => 'array',
            'tenants_may_hide' => 'boolean',
            'tenants_may_require' => 'boolean',
            'show_coming_soon' => 'boolean',
        ];
    }
}
