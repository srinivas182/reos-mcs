<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * A tenant's primary colour (A4.2), checked against REOS's high-contrast
 * standard: white text on it must reach 4.5:1 (WCAG AA for body text).
 * Colours that fail are refused with the nearest darker shade that passes.
 */
final readonly class BrandColour
{
    public const MIN_CONTRAST = 4.5;

    private function __construct(public string $hex) {}

    public static function parse(string $value): ?self
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^#?([0-9A-F]{6})$/', $value, $m) !== 1) {
            return null;
        }

        return new self('#'.$m[1]);
    }

    /** Contrast of white text on this colour (1 to 21). */
    public function contrastWithWhite(): float
    {
        return 1.05 / ($this->luminance() + 0.05);
    }

    public function readable(): bool
    {
        return $this->contrastWithWhite() >= self::MIN_CONTRAST;
    }

    /** The nearest darker shade white text is readable on (itself if it already is). */
    public function suggestion(): self
    {
        $colour = $this;

        for ($i = 0; $i < 40 && ! $colour->readable(); $i++) {
            $colour = $colour->mix('#000000', 0.05);
        }

        return $colour;
    }

    /** Hover and pressed: a little darker. */
    public function pressed(): self
    {
        return $this->mix('#000000', 0.18);
    }

    /** A pale tint for selected rows and chips. */
    public function tint(): self
    {
        return $this->mix('#FFFFFF', 0.9);
    }

    /** Mix towards another colour by a share (0 to 1). */
    public function mix(string $other, float $share): self
    {
        [$r1, $g1, $b1] = $this->rgb();
        [$r2, $g2, $b2] = (new self(strtoupper($other)))->rgb();
        $blend = static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $share);

        return new self(sprintf('#%02X%02X%02X', $blend($r1, $r2), $blend($g1, $g2), $blend($b1, $b2)));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(): array
    {
        return [(int) hexdec(substr($this->hex, 1, 2)), (int) hexdec(substr($this->hex, 3, 2)), (int) hexdec(substr($this->hex, 5, 2))];
    }

    private function luminance(): float
    {
        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = $this->rgb();

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
