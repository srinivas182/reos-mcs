<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Colour theme a person chooses. `System` follows the device's light or dark setting.
 * Matches the themes in packages/tokens.
 */
enum Appearance: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
    case Contrast = 'contrast';

    /** Value for `<html data-theme>`, or null when the browser decides (System). */
    public function theme(): ?string
    {
        return $this === self::System ? null : $this->value;
    }

    public static function fromCookie(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::System) : self::System;
    }
}
