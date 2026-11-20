<?php

declare(strict_types=1);

namespace App\Identity;

/** The installed app a person signs in on (one token per device). */
final readonly class DeviceInfo
{
    public function __construct(
        /** Random id the app creates on first launch and keeps. */
        public string $id,
        /** Shown in "Your devices", for example "Sofia's iPhone". */
        public string $name,
        public string $platform,
        public ?string $appVersion = null,
    ) {}
}
