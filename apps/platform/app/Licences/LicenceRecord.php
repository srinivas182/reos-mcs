<?php

declare(strict_types=1);

namespace App\Licences;

use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use Carbon\CarbonImmutable;

/** A licence as the state's regulator holds it (A5.1). */
final readonly class LicenceRecord
{
    public function __construct(
        public string $name,
        public LicenceType $type,
        /** Active, Inactive or Expired only. */
        public LicenceStatus $status,
        public ?CarbonImmutable $licensedOn,
        public ?CarbonImmutable $expiresOn,
        public ?string $sponsor,
    ) {}
}
