<?php

declare(strict_types=1);

namespace App\Studio\Video;

use App\Studio\StudioRenderer;
use GdImage;

/**
 * The pictures a studio video is built from (B12.2, ADR 0051), drawn with
 * GD and Inter so the words look the same with every renderer:
 *
 * - **still:** a listing photo cropped to fill the vertical frame, with a
 *   little room around it for the slow zoom;
 * - **caption:** a scene's words on a dark box in the lower third;
 * - **hook:** the first two seconds' words, large, near the top;
 * - **frame:** the band along the bottom with the agent and the broker on
 *   one line at one size (22 TAC §535.155: the broker's name is never
 *   smaller than the agent's), in the brand colour or a plain dark one;
 * - **poster:** the first scene with its words, for previews.
 *
 * Overlays are transparent PNGs the size of the video.
 */
final class VideoFrames
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    /** Room around a still for the slow zoom (10%). */
    public const STILL_WIDTH = 1188;

    public const STILL_HEIGHT = 2112;

    /** The band along the bottom. */
    public const BAND = 180;

    /** A plain dark frame when the agent turns the brand colour off. */
    public const PLAIN = '#111827';

    private const MARGIN = 80;

    /** A photo filling the vertical frame (JPEG); the brand colour if there's none. */
    public static function still(?GdImage $photo, string $colour, string $file): void
    {
        $im = imagecreatetruecolor(self::STILL_WIDTH, self::STILL_HEIGHT);
        [$r, $g, $b] = StudioRenderer::rgb($colour);
        imagefilledrectangle($im, 0, 0, self::STILL_WIDTH, self::STILL_HEIGHT, (int) imagecolorallocate($im, $r, $g, $b));

        if ($photo !== null) {
            StudioRenderer::cover($im, $photo, 0, 0, self::STILL_WIDTH, self::STILL_HEIGHT);
        }

        imagejpeg($im, $file, 90);
    }

    /** A scene's words on a dark box in the lower third. */
    public static function caption(string $text, string $file): void
    {
        $im = self::clear();
        $lines = self::wrap($text, 54, 'SemiBold', 3);
        $height = count($lines) * 76 + 56;
        $bottom = self::HEIGHT - self::BAND - 140;
        imagefilledrectangle($im, self::MARGIN - 28, $bottom - $height, self::WIDTH - self::MARGIN + 28, $bottom, (int) imagecolorallocatealpha($im, 0, 0, 0, 40));
        $white = (int) imagecolorallocate($im, 255, 255, 255);
        $y = $bottom - $height + 28 + 58;

        foreach ($lines as $line) {
            self::centred($im, $line, 54, 'SemiBold', $y, $white);
            $y += 76;
        }

        imagepng($im, $file, 6);
    }

    /** The first two seconds' words, large, near the top. */
    public static function hook(string $title, string $file): void
    {
        $im = self::clear();
        $lines = self::wrap($title, 92, 'Bold', 3);
        $height = count($lines) * 118 + 72;
        $top = 220;
        imagefilledrectangle($im, self::MARGIN - 28, $top, self::WIDTH - self::MARGIN + 28, $top + $height, (int) imagecolorallocatealpha($im, 0, 0, 0, 30));
        $white = (int) imagecolorallocate($im, 255, 255, 255);
        $y = $top + 36 + 96;

        foreach ($lines as $line) {
            self::centred($im, $line, 92, 'Bold', $y, $white);
            $y += 118;
        }

        imagepng($im, $file, 6);
    }

    /** The attribution's size on the band (the largest contact information on a video). */
    public const ATTRIBUTION_SIZE = 40;

    /** The band along the bottom: the agent and the broker at one size, on one line or two. */
    public static function frame(string $attribution, string $colour, string $file): void
    {
        $im = self::clear();
        [$r, $g, $b] = StudioRenderer::rgb($colour);
        imagefilledrectangle($im, 0, self::HEIGHT - self::BAND, self::WIDTH, self::HEIGHT, (int) imagecolorallocate($im, $r, $g, $b));
        $lines = self::attributionLines($attribution);
        $y = self::HEIGHT - (int) (self::BAND / 2) + 15 - (count($lines) - 1) * 24;

        foreach ($lines as $i => $line) {
            self::centred($im, $line, self::ATTRIBUTION_SIZE, 'SemiBold', $y + $i * 48, (int) imagecolorallocate($im, 255, 255, 255));
        }
        imagepng($im, $file, 6);
    }

    /**
     * The band's lines: one when it fits; otherwise the agent, then the
     * broker, each on its own line at the same size, so the broker's name
     * is never cut off (22 TAC §535.155).
     *
     * @return list<string>
     */
    public static function attributionLines(string $attribution): array
    {
        $one = self::wrap($attribution, self::ATTRIBUTION_SIZE, 'SemiBold', 1)[0] ?? '';

        if (! str_ends_with($one, '…')) {
            return [$one];
        }

        $parts = explode(' · ', $attribution, 2);
        $agent = self::wrap($parts[0], self::ATTRIBUTION_SIZE, 'SemiBold', 1);

        // The broker's name may take two lines; it's never shortened.
        return [...$agent, ...self::wrap($parts[1] ?? '', self::ATTRIBUTION_SIZE, 'SemiBold', 2)];
    }

    /**
     * The first scene with its words (JPEG).
     *
     * @param  list<string>  $overlays  PNG files drawn in order
     */
    public static function poster(string $still, array $overlays, string $file): void
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $source = @imagecreatefromstring((string) @file_get_contents($still));

        if ($source instanceof GdImage) {
            StudioRenderer::cover($im, $source, 0, 0, self::WIDTH, self::HEIGHT);
        }

        imagealphablending($im, true);

        foreach ($overlays as $overlay) {
            $layer = @imagecreatefrompng($overlay);

            if ($layer instanceof GdImage) {
                imagecopy($im, $layer, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);
            }
        }

        imagejpeg($im, $file, 85);
    }

    /**
     * Words broken into lines that fit the width; the last one shortened if there are more.
     *
     * @return list<string>
     */
    public static function wrap(string $text, int $size, string $weight, int $max): array
    {
        $width = self::WIDTH - 2 * self::MARGIN;
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : "{$line} {$word}";

            if ($line !== '' && self::width($try, $size, $weight) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] .= '…';
        }

        foreach ($lines as $i => $l) {
            while (mb_strlen($l) > 2 && self::width($l, $size, $weight) > $width) {
                $l = rtrim(mb_substr($l, 0, -2)).'…';
            }
            $lines[$i] = $l;
        }

        return array_values($lines);
    }

    private static function clear(): GdImage
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($im, false);
        imagefilledrectangle($im, 0, 0, self::WIDTH, self::HEIGHT, (int) imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        imagesavealpha($im, true);

        return $im;
    }

    private static function centred(GdImage $im, string $line, int $size, string $weight, int $y, int $colour): void
    {
        $x = (int) ((self::WIDTH - self::width($line, $size, $weight)) / 2);
        imagettftext($im, $size, 0, max(0, $x), $y, $colour, StudioRenderer::font($weight), $line);
    }

    private static function width(string $line, int $size, string $weight): int
    {
        $box = imagettfbbox($size, 0, StudioRenderer::font($weight), $line);

        return $box === false ? 0 : abs($box[2] - $box[0]);
    }
}
