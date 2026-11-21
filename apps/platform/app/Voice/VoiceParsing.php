<?php

declare(strict_types=1);

namespace App\Voice;

use App\Models\User;
use App\Services\Ai\AiCredits;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `POST /api/v1/voice/parse` (B4.3, ADR 0018): what someone said, turned
 * into to-dos for the confirm card. Claude when it's set up and the person
 * has credits (one per parse); the built-in parser otherwise, or whenever
 * Claude fails. It never fails the person: the confirm card always opens.
 * What was said is not stored or logged.
 */
final readonly class VoiceParsing
{
    public const FEATURE = 'voice.parse';

    public function __construct(
        private UnderstandingFactory $factory,
        private AiCredits $credits,
    ) {}

    /**
     * @param  'en'|'es'  $language
     * @param  CarbonImmutable  $saidAt  When it was said, in the speaker's time zone.
     * @return array{engine: string, items: list<array{title: string, notes: string|null, people: list<string>, due_at: string|null, due_date: string|null, remind_at: string|null}>, credits: array{used: int, left: int}}
     */
    public function parse(User $user, string $text, string $language, CarbonImmutable $saidAt): array
    {
        $ai = $this->credits->canSpend($user) ? $this->factory->ai() : null;
        $understanding = $ai ?? $this->factory->builtIn();
        $used = 0;

        try {
            $items = $understanding->understand($text, $language, $saidAt);
            if ($ai !== null) {
                $this->credits->charge($user, self::FEATURE, $ai->engine());
                $used = 1;
            }
        } catch (Throwable $e) {
            // The cause only; never what was said.
            Log::warning('Voice parsing fell back to the built-in parser.', ['error' => $e::class]);
            $understanding = $this->factory->builtIn();
            $items = $understanding->understand($text, $language, $saidAt);
        }

        return [
            'engine' => $understanding->engine(),
            'items' => array_map(static fn (array $item): array => self::shape($item, $saidAt), $items),
            'credits' => ['used' => $used, 'left' => $this->credits->left($user)],
        ];
    }

    /**
     * @param  array{title: string, notes: string|null, people: list<string>, due: array{date: string, time: string|null}|null, remind: bool}  $item
     * @return array{title: string, notes: string|null, people: list<string>, due_at: string|null, due_date: string|null, remind_at: string|null}
     */
    private static function shape(array $item, CarbonImmutable $saidAt): array
    {
        $due = $item['due'];
        $at = $due !== null && $due['time'] !== null
            ? CarbonImmutable::parse("{$due['date']} {$due['time']}", $saidAt->getTimezone())
            : null;

        return [
            'title' => $item['title'],
            'notes' => $item['notes'],
            'people' => $item['people'],
            'due_at' => $at?->toIso8601String(),
            'due_date' => $due !== null && $at === null ? $due['date'] : null,
            'remind_at' => $item['remind'] && $at !== null ? $at->toIso8601String() : null,
        ];
    }
}
