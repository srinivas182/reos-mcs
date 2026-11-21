<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

/** One calendar in a connected account, as the tool lists it. */
final readonly class CalendarInfo
{
    /**
     * @param  string  $id  The tool's id; Google's main calendar is `primary`.
     * @param  bool  $writable  The person may add and change events in it.
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $primary,
        public bool $writable,
    ) {}

    /** @return array{id: string, name: string, primary: bool, writable: bool} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'primary' => $this->primary, 'writable' => $this->writable];
    }

    /** @param  array<array-key, mixed>  $row */
    public static function fromArray(array $row): ?self
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($name)) {
            return null;
        }

        return new self($id, $name, ($row['primary'] ?? false) === true, ($row['writable'] ?? false) === true);
    }
}
