<?php

declare(strict_types=1);

namespace App\Mentoring;

use App\Models\SessionBooking;
use App\Services\Ai\Bedrock;
use App\Sessions\SessionBookings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Session wrap-up (A13.1b, ADR 0053; mentor screen 27): a short summary
 * of the session and its action items with dates, from the shared notes,
 * for the mentor to edit and send to the mentee's to-dos with reminders.
 * Claude writes it when it's configured; otherwise REOS takes the action
 * lines from the notes (A10.2a) and dates them a week out. Free.
 *
 * @phpstan-type Draft array{summary: string, tasks: list<array{title: string, due_date: string|null}>, engine: string}
 */
final readonly class WrapUp
{
    public const DAYS = 7;

    public function __construct(private Bedrock $bedrock) {}

    /** @return Draft */
    public function draft(SessionBooking $booking): array
    {
        $notes = (string) $booking->session?->notes;
        $due = CarbonImmutable::instance($booking->ends_at)->addDays(self::DAYS)->toDateString();
        $fallback = [
            'summary' => self::summaryFrom($booking->topic, $notes),
            'tasks' => array_map(static fn (string $title): array => ['title' => $title, 'due_date' => $due], SessionBookings::suggestedTasks($notes)),
            'engine' => 'template',
        ];
        $bedrock = trim($notes) === '' ? null : $this->bedrock->client(20);

        if ($bedrock === null) {
            return $fallback;
        }

        try {
            $written = (new ClaudeWrapUpWriter($bedrock['client'], $bedrock['model']))->write($booking->topic, $notes, CarbonImmutable::instance($booking->ends_at), $booking->student->locale->value);

            return [...$written, 'engine' => 'claude'];
        } catch (Throwable $e) {
            Log::warning('mentoring.wrap_up_failed', ['booking' => $booking->uuid, 'error' => $e->getMessage()]);

            return $fallback;
        }
    }

    /** The topic and the notes' first lines that aren't action items. */
    public static function summaryFrom(string $topic, string $notes): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $notes) ?: []), static fn (string $l): bool => $l !== '' && preg_match('/^\s*(?:[-*•]|\d+[.)])\s+/u', $l) !== 1));

        return Str::limit(trim($topic.'. '.implode(' ', array_slice($lines, 0, 3))), 600);
    }
}
