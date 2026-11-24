<?php

declare(strict_types=1);

use App\Studio\Video\Music;
use App\Studio\Video\Pauses;

it('keeps the speech and takes out long pauses, with a little air', function () {
    $log = "[silencedetect] silence_start: 1.5\n[silencedetect] silence_end: 3.5 | silence_duration: 2\n[silencedetect] silence_start: 4.2\n[silencedetect] silence_end: 4.6 | silence_duration: 0.4\n";
    expect(Pauses::keep($log, 6.0))->toBe([[0.0, 1.65], [3.35, 6.0]])
        ->and(Pauses::expression([[0.0, 1.65], [3.35, 6.0]]))->toBe('between(t\,0.00\,1.65)+between(t\,3.35\,6.00)');
});

it('keeps everything when there are no pauses, or when it would leave almost nothing', function () {
    expect(Pauses::keep('', 4.0))->toBe([[0.0, 4.0]])
        ->and(Pauses::keep("silence_start: 0\nsilence_end: 4.0", 4.0))->toBe([[0.0, 4.0]])
        ->and(Pauses::keep("silence_start: 3.0\n", 4.0))->toBe([[0.0, 3.15]]);
});

it('has REOS-made tracks and none', function () {
    expect(Music::expression('calm'))->toContain('sin(')->and(Music::expression('bright'))->toContain('exp(')->and(Music::expression('none'))->toBeNull();
});
