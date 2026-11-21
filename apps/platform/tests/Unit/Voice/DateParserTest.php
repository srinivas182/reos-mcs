<?php

declare(strict_types=1);

use App\Voice\DateParser;
use Carbon\CarbonImmutable;

/*
 * The built-in parser (B4.3) passes the phrase set the phone's TypeScript
 * copy passes too: packages/voice/src/phrases.json.
 */

$phrases = json_decode((string) file_get_contents(__DIR__.'/../../../../../packages/voice/src/phrases.json'), true);

foreach ($phrases['cases'] as $case) {
    it("understands {$case['language']}: {$case['text']}", function () use ($case, $phrases) {
        expect(DateParser::parse($case['text'], $case['language'], CarbonImmutable::parse($phrases['now'], 'America/Chicago')))->toBe($case['expect']);
    });
}
