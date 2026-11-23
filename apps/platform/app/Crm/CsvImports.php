<?php

declare(strict_types=1);

namespace App\Crm;

use App\Jobs\ImportContactsCsv;
use App\Models\Connection;
use App\Models\ContactImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SplFileObject;

/**
 * CSV import, the last resort (B8.3b, ADR 0034): upload, map the columns,
 * preview who is new and who joins a contact already there (on email or
 * phone, like any source), then import. The rows become a source named
 * "CSV import, date" that can be undone: people only the CSV knew go, and
 * the others lose only the CSV's link.
 *
 * @phpstan-type Preview array{new: int, merged: int, skipped: int, samples: list<array{row: int, name: string, email: string|null, phone: string|null, outcome: string, joins: string|null}>}
 */
final readonly class CsvImports
{
    public const MAX_ROWS = 5000;

    /** Header words that suggest a field, lower case. */
    private const GUESSES = [
        'first_name' => ['first name', 'firstname', 'first', 'given name', 'nombre'],
        'last_name' => ['last name', 'lastname', 'last', 'surname', 'family name', 'apellido'],
        'full_name' => ['name', 'full name', 'contact', 'contact name'],
        'email' => ['email', 'e-mail', 'email address', 'correo'],
        'phone' => ['phone', 'mobile', 'cell', 'phone number', 'mobile phone', 'teléfono', 'telefono'],
        'stage' => ['stage', 'status', 'etapa'],
        'tags' => ['tags', 'tag', 'labels', 'groups'],
        'birthday' => ['birthday', 'birth date', 'date of birth', 'dob'],
    ];

    public function __construct(private Contacts $contacts) {}

    /** Reads the header and counts the rows; guesses the mapping. */
    public function upload(User $agent, UploadedFile $file): ContactImport
    {
        $uuid = (string) Str::uuid7();
        $path = $file->storeAs('contact-imports', $uuid.'.csv', 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('crm.import.errors.unreadable')]);
        }

        $headers = null;
        $rows = 0;
        foreach ($this->read(Storage::disk('local')->path($path)) as $n => $row) {
            if ($n === 0) {
                $headers = $row;

                continue;
            }
            $rows++;
        }

        if ($headers === null || $headers === [] || $rows === 0) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages(['file' => __('crm.import.errors.empty')]);
        }

        if ($rows > self::MAX_ROWS) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages(['file' => __('crm.import.errors.tooMany', ['max' => self::MAX_ROWS])]);
        }

        return ContactImport::query()->create([
            'uuid' => $uuid, 'user_id' => $agent->id, 'file_name' => mb_substr($file->getClientOriginalName(), 0, 191), 'path' => $path,
            'headers' => $headers, 'mapping' => self::guess($headers), 'rows' => $rows, 'status' => 'uploaded',
        ]);
    }

    /**
     * Saves the mapping and says what an import would do, row by row.
     *
     * @param  array<string, string>  $mapping  Column header => field (or "ignore").
     * @return Preview
     */
    public function preview(ContactImport $import, array $mapping): array
    {
        $mapping = array_filter($mapping, static fn (string $field, string $header): bool => in_array($field, ContactImport::FIELDS, true) && in_array($header, $import->headers, true), ARRAY_FILTER_USE_BOTH);

        if (! array_intersect($mapping, ['email', 'phone', 'full_name', 'first_name', 'last_name'])) {
            throw ValidationException::withMessages(['mapping' => __('crm.import.errors.noName')]);
        }

        $import->forceFill(['mapping' => $mapping])->save();
        $counts = ['new' => 0, 'merged' => 0, 'skipped' => 0];
        $samples = [];
        $seen = [];

        foreach ($this->records($import) as $n => $record) {
            if ($record === null) {
                $counts['skipped']++;
                $outcome = 'skipped';
                $joins = null;
            } else {
                $match = $this->contacts->wouldJoin(Connection::OWNER_USER, $import->user_id, ['external_id' => 'row-'.$n, ...$record]);
                // Two rows for the same person in the file (same email or phone): the second joins the first.
                $keys = [...array_map(static fn (string $e): string => 'e:'.mb_strtolower($e), $record['emails']), ...array_map(static fn (string $p): string => 'p:'.substr((string) preg_replace('/\D+/', '', $p), -10), $record['phones'])];
                $repeat = $match === null && array_intersect_key($seen, array_flip($keys)) !== [];
                $seen += array_fill_keys($keys, true);
                $outcome = $match !== null || $repeat ? 'merged' : 'new';
                $counts[$outcome]++;
                $joins = $match?->name();
            }

            if (count($samples) < 10) {
                $samples[] = [
                    'row' => $n, 'name' => trim(($record['first_name'] ?? '').' '.($record['last_name'] ?? '')),
                    'email' => $record['emails'][0] ?? null, 'phone' => $record['phones'][0] ?? null, 'outcome' => $outcome, 'joins' => $joins,
                ];
            }
        }

        return [...$counts, 'samples' => $samples];
    }

    /** Imports in the background; the contacts page shows the result. */
    public function import(ContactImport $import): void
    {
        if ($import->status !== 'uploaded' || $import->mapping === null) {
            throw ValidationException::withMessages(['import' => __('crm.import.errors.state')]);
        }

        ImportContactsCsv::dispatch($import->id);
    }

    /** The queued half: every row through the same matching as any source. */
    public function run(int $importId): void
    {
        $import = ContactImport::query()->find($importId);

        if ($import === null || $import->status !== 'uploaded') {
            return;
        }

        $counts = ['new' => 0, 'merged' => 0, 'skipped' => 0];

        foreach ($this->records($import) as $n => $record) {
            if ($record === null) {
                $counts['skipped']++;

                continue;
            }

            $before = $this->contacts->wouldJoin(Connection::OWNER_USER, $import->user_id, ['external_id' => 'row-'.$n, ...$record]);
            $this->contacts->receive(Connection::OWNER_USER, $import->user_id, $import->source(), null, ['external_id' => 'row-'.$n, ...$record]);
            $counts[$before === null ? 'new' : 'merged']++;
        }

        $this->discard($import);
        $import->forceFill(['status' => 'imported', 'counts' => $counts, 'imported_at' => CarbonImmutable::now()])->save();
    }

    /** Undo: people only this CSV knew go; the others keep everything else. */
    public function undo(ContactImport $import): void
    {
        if ($import->status === 'uploaded') {
            $this->discard($import);
            $import->delete();

            return;
        }

        if ($import->status !== 'imported') {
            throw ValidationException::withMessages(['import' => __('crm.import.errors.state')]);
        }

        $this->contacts->forget(Connection::OWNER_USER, $import->user_id, $import->source());
        $import->forceFill(['status' => 'undone', 'undone_at' => CarbonImmutable::now()])->save();
    }

    /**
     * The rows as contact records (null: nothing to match on).
     *
     * @return Generator<int, array{first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null, tags: list<string>, birthday: string|null}|null>
     */
    private function records(ContactImport $import): Generator
    {
        if ($import->path === null || ! Storage::disk('local')->exists($import->path)) {
            return;
        }

        $mapping = $import->mapping ?? [];
        $columns = [];
        foreach ($import->headers as $i => $header) {
            if (isset($mapping[$header])) {
                $columns[$i] = $mapping[$header];
            }
        }

        foreach ($this->read(Storage::disk('local')->path($import->path)) as $n => $row) {
            if ($n === 0) {
                continue;
            }

            $values = [];
            foreach ($columns as $i => $field) {
                $value = trim($row[$i] ?? '');
                if ($value !== '') {
                    $values[$field] = $value;
                }
            }

            [$first, $last] = isset($values['first_name']) || isset($values['last_name'])
                ? [$values['first_name'] ?? null, $values['last_name'] ?? null]
                : self::split($values['full_name'] ?? null);
            $emails = isset($values['email']) ? array_values(array_filter(preg_split('/[;,\s]+/', $values['email']) ?: [])) : [];
            $phones = isset($values['phone']) ? array_values(array_filter(array_map('trim', preg_split('/[;,\/]+/', $values['phone']) ?: []))) : [];
            $birthday = isset($values['birthday']) && strtotime($values['birthday']) !== false ? CarbonImmutable::parse($values['birthday'])->toDateString() : null;

            yield $n => $emails === [] && $phones === [] && $first === null && $last === null ? null : [
                'first_name' => $first, 'last_name' => $last, 'emails' => $emails, 'phones' => $phones,
                'stage' => $values['stage'] ?? null, 'birthday' => $birthday,
                'tags' => isset($values['tags']) ? array_values(array_filter(array_map('trim', explode(',', $values['tags'])))) : [],
            ];
        }
    }

    /**
     * The file's rows, header first. UTF-8 (a BOM is dropped); commas or semicolons.
     *
     * @return Generator<int, list<string>>
     */
    private function read(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $first = (string) $file->fgets();
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter, '"', '');
        $n = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $cells = array_map(static fn (mixed $cell): string => is_string($cell) ? $cell : '', $row);
            if ($n === 0) {
                $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0] ?? '');
                $cells = array_map('trim', $cells);
            }

            yield $n++ => array_values($cells);
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private static function guess(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $word = mb_strtolower(trim($header));
            foreach (self::GUESSES as $field => $words) {
                if (in_array($word, $words, true) && ! in_array($field, $mapping, true)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    /** @return array{0: string|null, 1: string|null} "Maria Lopez" → Maria, Lopez; "Lopez, Maria" too. */
    private static function split(?string $name): array
    {
        if ($name === null) {
            return [null, null];
        }

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return [$first !== '' ? $first : null, $last !== '' ? $last : null];
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = array_shift($parts);

        return [$first, $parts === [] ? null : implode(' ', $parts)];
    }

    private function discard(ContactImport $import): void
    {
        if ($import->path !== null) {
            Storage::disk('local')->delete($import->path);
            $import->forceFill(['path' => null])->save();
        }
    }
}
