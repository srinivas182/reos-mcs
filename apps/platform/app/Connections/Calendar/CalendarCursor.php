<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

/**
 * Where a calendar sync stands across several calendars (saved as
 * the `events` stream's cursor after every page):
 *
 * - `tokens`: each calendar's `nextSyncToken`, for changes only next time.
 * - `pending`: the calendars still to read in this pass, current first.
 * - `page` and `since`: the current calendar's next page, and the window it
 *   was asked with (tools want the same question on every page).
 *
 * A pass ends when no calendar is pending; the next one starts with every
 * synced calendar again. Calendars no longer synced are dropped.
 */
final class CalendarCursor
{
    /**
     * @param  array<string, string>  $tokens
     * @param  list<string>  $pending
     */
    private function __construct(
        private array $tokens,
        private array $pending,
        public ?string $page,
        public ?string $since,
    ) {}

    /** @param  list<string>  $synced  Calendars REOS reads now. */
    public static function open(?string $cursor, array $synced): self
    {
        $state = $cursor === null ? [] : (array) json_decode($cursor, true);
        $tokens = [];

        foreach ((array) ($state['tokens'] ?? []) as $calendar => $token) {
            if (is_string($token) && in_array((string) $calendar, $synced, true)) {
                $tokens[(string) $calendar] = $token;
            }
        }

        $saved = array_values(array_filter((array) ($state['pending'] ?? []), is_string(...)));
        $pending = array_values(array_filter($saved, static fn (string $calendar): bool => in_array($calendar, $synced, true)));
        $carryOn = $pending !== [] && ($saved[0] ?? null) === $pending[0];

        if ($pending === []) {
            // A new pass over every synced calendar.
            return new self($tokens, $synced, null, null);
        }

        // Paging belongs to the calendar it started on.
        return new self(
            $tokens,
            $pending,
            $carryOn && is_string($state['page'] ?? null) ? $state['page'] : null,
            $carryOn && is_string($state['since'] ?? null) ? $state['since'] : null,
        );
    }

    /** The calendar to read now, or null when the pass is done. */
    public function calendar(): ?string
    {
        return $this->pending[0] ?? null;
    }

    public function syncToken(string $calendar): ?string
    {
        return $this->tokens[$calendar] ?? null;
    }

    public function nextPage(string $page): self
    {
        $this->page = $page;

        return $this;
    }

    /** The current calendar is up to date; move to the next. */
    public function finishCalendar(?string $syncToken): void
    {
        $calendar = array_shift($this->pending);

        if ($calendar !== null && $syncToken !== null) {
            $this->tokens[$calendar] = $syncToken;
        }

        $this->page = null;
        $this->since = null;
    }

    public function encode(): string
    {
        return json_encode(array_filter([
            'tokens' => $this->tokens,
            'pending' => $this->pending,
            'page' => $this->page,
            'since' => $this->since,
        ], static fn (mixed $value): bool => $value !== null && $value !== []), JSON_THROW_ON_ERROR);
    }
}
