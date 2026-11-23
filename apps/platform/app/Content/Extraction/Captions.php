<?php

declare(strict_types=1);

namespace App\Content\Extraction;

/** Reads WebVTT captions into lines with the second each starts at (A5.3), and writes them for AI lessons (A7.3). */
final class Captions
{
    /** @return list<array{seconds: int, text: string}> */
    public static function cues(string $vtt): array
    {
        $vtt = str_replace(["\r\n", "\r"], "\n", ltrim($vtt, "\xEF\xBB\xBF"));
        $cues = [];

        foreach (preg_split("/\n{2,}/", $vtt) ?: [] as $block) {
            $lines = explode("\n", trim($block));
            $timing = null;

            foreach ($lines as $index => $line) {
                if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})[.,]\d{3}\s+-->/', $line, $match) === 1) {
                    $timing = ((int) $match[1]) * 3600 + ((int) $match[2]) * 60 + (int) $match[3];
                    $text = trim(strip_tags(implode(' ', array_slice($lines, $index + 1))));

                    if ($text !== '') {
                        $cues[] = ['seconds' => $timing, 'text' => html_entity_decode($text, ENT_QUOTES | ENT_HTML5)];
                    }

                    break;
                }
            }
        }

        return $cues;
    }

    /**
     * Captions for a video of so many seconds, one cue per line, each line's
     * time in proportion to its length (vendors give the video's length,
     * not each line's).
     *
     * @param  list<string>  $lines
     */
    public static function write(array $lines, int $seconds): string
    {
        $lengths = array_map(static fn (string $line): int => max(1, mb_strlen($line)), $lines);
        $total = max(1, array_sum($lengths));
        $milliseconds = max(1, $seconds) * 1000;
        $vtt = "WEBVTT\n";
        $at = 0;

        foreach ($lines as $i => $line) {
            $end = $i === count($lines) - 1 ? $milliseconds : $at + (int) floor($milliseconds * $lengths[$i] / $total);
            $text = htmlspecialchars(str_replace(["\r", "\n"], ' ', $line), ENT_NOQUOTES);
            $vtt .= "\n".($i + 1)."\n".self::time($at).' --> '.self::time($end)."\n".$text."\n";
            $at = $end;
        }

        return $vtt;
    }

    private static function time(int $milliseconds): string
    {
        return sprintf('%02d:%02d:%02d.%03d', intdiv($milliseconds, 3600000), intdiv($milliseconds % 3600000, 60000), intdiv($milliseconds % 60000, 1000), $milliseconds % 1000);
    }
}
