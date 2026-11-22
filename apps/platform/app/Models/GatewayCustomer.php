<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The person's customer id at a payment gateway (B5.1), one per gateway and
 * environment (test customers aren't live customers).
 *
 * @property int $id
 * @property int $user_id
 * @property string $gateway
 * @property string $environment
 * @property string $customer_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class GatewayCustomer extends Model
{
    protected $fillable = ['user_id', 'gateway', 'environment', 'customer_id'];
}
