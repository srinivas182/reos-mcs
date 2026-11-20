<?php

declare(strict_types=1);

namespace App\Models;

use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Encrypted keys for one integration and environment. Read and write only
 * through {@see CredentialVault}.
 *
 * @property int $id
 * @property string $integration_id
 * @property CredentialEnvironment $environment
 * @property string $payload
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['integration_id', 'environment', 'payload', 'updated_by'])]
#[Hidden(['payload'])]
final class IntegrationCredential extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['environment' => CredentialEnvironment::class];
    }
}
