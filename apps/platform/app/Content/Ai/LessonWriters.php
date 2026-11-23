<?php

declare(strict_types=1);

namespace App\Content\Ai;

use App\Services\Ai\Bedrock;

/** Claude when "Claude on Amazon Bedrock" has test or live keys; otherwise the simulator (A7.3). */
final readonly class LessonWriters
{
    public function __construct(private Bedrock $bedrock) {}

    public function writer(): LessonWriter
    {
        $bedrock = $this->bedrock->client(120);

        return $bedrock === null ? new SimulatedLessonWriter : new ClaudeLessonWriter($bedrock['client'], $bedrock['model'], self::glossary());
    }

    /** The Spanish glossary's term table (packages/i18n/GLOSSARY.md), when the repository ships with the app. */
    private static function glossary(): string
    {
        $path = base_path('../../packages/i18n/GLOSSARY.md');
        $text = is_readable($path) ? (string) file_get_contents($path) : '';
        $rows = array_filter(explode("\n", $text), static fn (string $line): bool => str_starts_with($line, '| ') && ! str_starts_with($line, '| English') && ! str_starts_with($line, '| ---'));

        return mb_substr(implode("\n", $rows), 0, 12000);
    }
}
