<?php

declare(strict_types=1);

namespace App\Studio;

use App\Social\SocialPosts;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Designs for the content studio (B12.1, ADR 0050): square 1080 × 1080 PNGs
 * from a photo and the brand kit (the brokerage's colour), drawn with GD
 * and Inter. Every design carries the agent and the broker on one line at
 * the same size, so the broker's name is never smaller than half the
 * largest contact information (22 TAC §535.155(a)(2)).
 *
 * Three layouts: a band along the bottom, a band along the top, and a
 * split with the words on the brand colour. A photo that can't be read
 * (or none) leaves the brand colour behind the words.
 */
final class StudioRenderer
{
    public const SIZE = 1080;

    public const LAYOUTS = ['band', 'top', 'split'];

    /** The attribution's size: the agent and the broker, the largest contact information on a design. */
    public const ATTRIBUTION_SIZE = 30;

    /**
     * @param  array{headline: string, lines: list<string>, attribution: string}  $text
     */
    public static function design(string $layout, ?string $photo, string $colour, array $text, ?string $portrait = null): string
    {
        $im = imagecreatetruecolor(self::SIZE, self::SIZE);
        [$r, $g, $b] = self::rgb($colour);
        $brand = (int) imagecolorallocate($im, $r, $g, $b);
        $white = (int) imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, self::SIZE, self::SIZE, $brand);
        $source = $photo === null ? null : self::photo($photo);

        if ($layout === 'split') {
            if ($source !== null) {
                self::cover($im, $source, 0, 0, self::SIZE, 620);
            }
            self::words($im, $text, 60, 720, $white);
        } else {
            if ($source !== null) {
                self::cover($im, $source, 0, 0, self::SIZE, self::SIZE);
            }
            $shade = (int) imagecolorallocatealpha($im, max(0, min(255, $r - 40)), max(0, min(255, $g - 40)), max(0, min(255, $b - 40)), 6);
            $top = $layout === 'top' ? 0 : 700;
            imagefilledrectangle($im, 0, $top, self::SIZE, $top + 380, $shade);
            self::words($im, $text, 60, $top + 100, $white);
        }

        // The agent's profile photo in a circle (B12.3, celebration designs).
        $face = $portrait === null ? null : self::photo($portrait);

        if ($face !== null) {
            self::circle($im, $face, self::SIZE - 300, $layout === 'top' ? self::SIZE - 300 : 40, 260, $white);
        }

        ob_start();
        imagepng($im, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * @param  array{headline: string, lines: list<string>, attribution: string}  $text
     */
    private static function words(GdImage $im, array $text, int $x, int $y, int $colour): void
    {
        imagettftext($im, 64, 0, $x, $y, $colour, self::font('Bold'), self::fit($text['headline'], 64, 'Bold'));
        $y += 78;

        foreach (array_slice($text['lines'], 0, 2) as $line) {
            imagettftext($im, 36, 0, $x, $y, $colour, self::font('Regular'), self::fit($line, 36, 'Regular'));
            $y += 56;
        }

        // The agent and the broker at one size: one line, or the broker on its own line so it's never cut off.
        foreach (self::attributionLines($text['attribution']) as $i => $line) {
            imagettftext($im, self::ATTRIBUTION_SIZE, 0, $x, $y + 20 + $i * 40, $colour, self::font('SemiBold'), $line);
        }
    }

    /**
     * The design's attribution lines: one when it fits, else the agent and
     * the broker on two lines at the same size.
     *
     * @return list<string>
     */
    public static function attributionLines(string $attribution): array
    {
        $one = self::fit($attribution, self::ATTRIBUTION_SIZE, 'SemiBold');

        if ($one === $attribution) {
            return [$one];
        }

        return array_map(static fn (string $part): string => self::fit($part, self::ATTRIBUTION_SIZE, 'SemiBold'), array_slice(explode(' · ', $attribution), 0, 2));
    }

    /** Shortens a line until it fits the width. */
    private static function fit(string $line, int $size, string $weight): string
    {
        $max = self::SIZE - 120;

        while (mb_strlen($line) > 4) {
            $box = imagettfbbox($size, 0, self::font($weight), $line);

            if ($box === false || abs($box[2] - $box[0]) <= $max) {
                return $line;
            }

            $line = rtrim(mb_substr($line, 0, -2)).'…';
        }

        return $line;
    }

    /** Scales and crops the photo to fill the box. */
    public static function cover(GdImage $im, GdImage $photo, int $x, int $y, int $w, int $h): void
    {
        $pw = imagesx($photo);
        $ph = imagesy($photo);
        $scale = max($w / $pw, $h / $ph);
        $sw = (int) round($w / $scale);
        $sh = (int) round($h / $scale);
        imagecopyresampled($im, $photo, $x, $y, (int) (($pw - $sw) / 2), (int) (($ph - $sh) / 2), $w, $h, $sw, $sh);
    }

    /** A photo cropped to a circle with a white ring. */
    private static function circle(GdImage $im, GdImage $photo, int $x, int $y, int $size, int $ring): void
    {
        $square = imagecreatetruecolor(max(1, $size), max(1, $size));
        self::cover($square, $photo, 0, 0, $size, $size);
        imagefilledellipse($im, $x + intdiv($size, 2), $y + intdiv($size, 2), $size + 16, $size + 16, $ring);
        $r = $size / 2;

        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                if (($px - $r + 0.5) ** 2 + ($py - $r + 0.5) ** 2 <= $r * $r) {
                    imagesetpixel($im, $x + $px, $y + $py, (int) imagecolorat($square, $px, $py));
                }
            }
        }
    }

    /** A photo from REOS's demo listings, the social media disk, or the web (JPEG or PNG). */
    public static function photo(string $photo): ?GdImage
    {
        try {
            if (preg_match('#/demo/listings/(\d)\.svg$#', $photo, $m) === 1) {
                $bytes = @file_get_contents(public_path("demo/listings/{$m[1]}.png"));
            } elseif (str_starts_with($photo, 'social/')) {
                $bytes = Storage::disk(SocialPosts::MEDIA_DISK)->get($photo);
            } elseif (str_starts_with($photo, 'https://')) {
                $response = Http::timeout(10)->get($photo);
                $bytes = $response->successful() ? $response->body() : null;
            } else {
                $bytes = null;
            }

            $image = is_string($bytes) && $bytes !== '' ? @imagecreatefromstring($bytes) : false;

            return $image instanceof GdImage ? $image : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
            $hex = '1f3a5f';
        }

        return [self::byte(substr($hex, 0, 2)), self::byte(substr($hex, 2, 2)), self::byte(substr($hex, 4, 2))];
    }

    /** @return int<0, 255> */
    private static function byte(string $hex): int
    {
        return max(0, min(255, (int) hexdec($hex)));
    }

    public static function font(string $weight): string
    {
        return resource_path("fonts/Inter-{$weight}.otf");
    }
}
