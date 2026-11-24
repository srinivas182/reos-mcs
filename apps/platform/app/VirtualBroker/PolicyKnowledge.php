<?php

declare(strict_types=1);

namespace App\VirtualBroker;

use App\Content\Extraction\Section;
use App\Content\Extraction\TextExtractor;
use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\PolicyPassage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * What the virtual broker knows about a brokerage (A12.1, ADR 0047; broker
 * web screen 34, broker journey screen 5).
 *
 * - **The manual:** a PDF, Word or text file. Each upload is a new version;
 *   the older ones are kept (file and record) but no longer answered from.
 *   The text is split into passages of a few paragraphs with their page
 *   and heading, the topics are found, and passages that contradict a
 *   TREC rule are listed: the TREC rule wins.
 * - **The FAQ:** answers the broker chose to add, removable with Undo.
 *
 * @phpstan-import-type Conflict from TexasConflicts
 */
final readonly class PolicyKnowledge
{
    /** Topics found in a manual, by the words that mark them. */
    public const TOPICS = [
        'advertising' => '/\b(advertis\w*|social media|yard signs?|marketing)\b/u',
        'splits' => '/\b(splits?|commissions?|caps?|desk fees?)\b/u',
        'escrow' => '/\b(earnest money|escrow|trust (account|money)|option fees?)\b/u',
        'coordinators' => '/\b(transaction coordinators?|coordinators?|assistants?)\b/u',
        'leads' => '/\b(leads?|referrals?|lead routing)\b/u',
        'forms' => '/\b(contracts?|forms?|addend(a|um))\b/u',
        'supervision' => '/\b(supervis\w*|mentors?|training)\b/u',
        'fair_housing' => '/\b(fair housing|discriminat\w*)\b/u',
    ];

    private const CHUNK = 900;

    public function __construct(private TextExtractor $extractor, private PolicySearch $search, private AuditLogger $audit) {}

    /** A new version of the manual becomes current. */
    public function upload(Organisation $brokerage, UploadedFile $file, User $actor): PolicyManual
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $sections = $this->read($file, $extension);
        $passages = self::passages($sections);

        if ($passages === []) {
            throw ValidationException::withMessages(['file' => self::line('virtualBroker.errors.noText')]);
        }

        $state = ($brokerage->states ?? [])[0] ?? 'TX';
        $conflicts = TexasConflicts::find($passages, $this->search->stateRules($state));
        $uuid = (string) Str::uuid7();
        $path = "tenants/{$brokerage->id}/policy/{$uuid}.{$extension}";
        Storage::disk(self::disk())->putFileAs(dirname($path), $file, basename($path));

        $manual = DB::transaction(function () use ($brokerage, $file, $actor, $sections, $passages, $conflicts, $uuid, $path): PolicyManual {
            // Two uploads at once wait for each other, so versions never collide.
            Organisation::query()->whereKey($brokerage->id)->lockForUpdate()->first();
            $last = PolicyManual::query()->where('organisation_id', $brokerage->id)->max('version');
            $version = (is_numeric($last) ? (int) $last : 0) + 1;
            PolicyManual::query()->where('organisation_id', $brokerage->id)->where('status', PolicyManual::CURRENT)->update(['status' => PolicyManual::REPLACED, 'updated_at' => now()]);
            $text = mb_strtolower(implode(' ', array_map(static fn (array $p): string => ($p['heading'] ?? '').' '.$p['content'], $passages)));
            $manual = PolicyManual::query()->create([
                'uuid' => $uuid, 'organisation_id' => $brokerage->id, 'version' => $version,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 200), 'path' => $path,
                'pages' => max(1, count(array_filter($sections, static fn (Section $s): bool => $s->page !== null))),
                'passages' => count($passages),
                'topics' => array_keys(array_filter(self::TOPICS, static fn (string $pattern): bool => preg_match($pattern, $text) === 1)),
                'conflicts' => $conflicts, 'status' => PolicyManual::CURRENT, 'uploaded_by' => $actor->id,
            ]);

            foreach (array_chunk($passages, 200) as $chunk) {
                PolicyPassage::query()->insert(array_map(static fn (array $p): array => [
                    'organisation_id' => $brokerage->id, 'policy_manual_id' => $manual->id, 'kind' => PolicyPassage::MANUAL, 'heading' => $p['heading'],
                    'page' => $p['page'], 'position' => $p['position'], 'content' => $p['content'], 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }

            return $manual;
        });

        $this->audit->record('policy_manual.uploaded', 'policy_manual', $manual->uuid, ['version' => $manual->version, 'passages' => $manual->passages, 'conflicts' => count($conflicts)], $actor, $brokerage->id);

        return $manual;
    }

    /** The current manual, if one has been uploaded. */
    public function current(Organisation $brokerage): ?PolicyManual
    {
        return PolicyManual::query()->where('organisation_id', $brokerage->id)->where('status', PolicyManual::CURRENT)->latest('version')->first();
    }

    /** A broker's answer becomes part of what every agent gets. */
    public function addFaq(Organisation $brokerage, string $question, string $answer, ?int $questionId): PolicyPassage
    {
        return PolicyPassage::query()->create([
            'organisation_id' => $brokerage->id, 'kind' => PolicyPassage::FAQ, 'heading' => mb_substr(trim($question), 0, 300),
            'content' => trim($answer), 'broker_question_id' => $questionId,
        ]);
    }

    /** Remove an FAQ entry, or put it back (Undo). */
    public function removeFaq(Organisation $brokerage, int $id, bool $restore, User $actor): PolicyPassage
    {
        $query = PolicyPassage::query()->where('organisation_id', $brokerage->id)->where('kind', PolicyPassage::FAQ);
        $entry = ($restore ? $query->onlyTrashed() : $query)->findOrFail($id);
        $restore ? $entry->restore() : $entry->delete();
        $this->audit->record($restore ? 'policy_faq.restored' : 'policy_faq.removed', 'policy_passage', (string) $entry->id, ['question' => $entry->heading], $actor, $brokerage->id);

        return $entry;
    }

    /**
     * The file's words, page by page (PDF) or as one document.
     *
     * @return list<Section>
     */
    private function read(UploadedFile $file, string $extension): array
    {
        if (in_array($extension, ['txt', 'md'], true)) {
            $text = file_get_contents($file->getRealPath());

            return [new Section(null, is_string($text) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : '')];
        }

        try {
            return $this->extractor->extract($file->getRealPath(), $extension);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['file' => self::line('virtualBroker.errors.unreadable')]);
        }
    }

    /**
     * Passages of a few paragraphs, each with the heading it sits under and its page.
     *
     * @param  list<Section>  $sections
     * @return list<array{heading: string|null, page: int|null, position: int, content: string}>
     */
    public static function passages(array $sections): array
    {
        $passages = [];
        $heading = null;
        $position = 0;

        foreach ($sections as $section) {
            $buffer = '';
            $flush = static function () use (&$buffer, &$passages, &$position, &$heading, $section): void {
                if (trim($buffer) !== '') {
                    $passages[] = ['heading' => $heading, 'page' => $section->page, 'position' => $position++, 'content' => trim($buffer)];
                }
                $buffer = '';
            };

            foreach (preg_split('/\n\s*\n|\r\n\s*\r\n/u', $section->text) ?: [] as $paragraph) {
                $paragraph = trim($paragraph);

                if ($paragraph === '') {
                    continue;
                }

                $lines = preg_split('/\R/u', $paragraph) ?: [$paragraph];
                $first = trim($lines[0]);

                if (self::isHeading($first)) {
                    $flush();
                    $heading = mb_substr(trim(ltrim($first, '# ')), 0, 300);
                    $paragraph = trim(implode("\n", array_slice($lines, 1)));

                    if ($paragraph === '') {
                        continue;
                    }
                }

                if (mb_strlen($buffer) + mb_strlen($paragraph) > self::CHUNK) {
                    $flush();
                }

                $buffer .= ($buffer === '' ? '' : "\n\n").$paragraph;
            }

            $flush();
        }

        return $passages;
    }

    private static function isHeading(string $line): bool
    {
        if (str_starts_with($line, '#')) {
            return true;
        }

        if (mb_strlen($line) > 80 || preg_match('/[.:;,]$/u', $line) === 1) {
            return false;
        }

        // "4.2 Earnest money", "Section 7 Advertising" or "ADVERTISING".
        return preg_match('/^(\d+(\.\d+)*\.?\s+\p{L}|section\s+\d+)/iu', $line) === 1
            || (mb_strlen($line) >= 4 && preg_match('/^[\p{Lu}0-9 ,&\-\/]+$/u', $line) === 1);
    }

    private static function disk(): string
    {
        return config()->string('reos.content.disk');
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
