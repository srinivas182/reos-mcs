<?php

declare(strict_types=1);

namespace App\Content\Extraction;

/** Reads WebVTT captions into lines with the second each starts at (A5.3). */
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
}
