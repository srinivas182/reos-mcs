<?php

declare(strict_types=1);

namespace App\ContractReview;

use App\Authorization\Role;
use App\Content\Extraction\TextExtractor;
use App\Deals\DealFiles;
use App\Deals\Deals;
use App\Enums\OrganisationType;
use App\Jobs\ReviewContract;
use App\Models\ContractReview;
use App\Models\Deal;
use App\Models\DealFile;
use App\Models\User;
use App\Notifications\ContractReviewReady;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Bedrock;
use App\Services\Authorization\Memberships;
use App\Services\Content\LessonFiles;
use App\Services\StateRules\StateRules;
use App\Services\Todos\Todos;
use App\VirtualBroker\PolicySearch;
use App\VirtualBroker\VirtualBroker;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Contract review (A12.2, ADR 0048; agent screens 63–66, web screen 15).
 *
 * 1. **Choose** a PDF or photos of pages, a deal's document, or (outside
 *    production) the sample TREC 20-18. A new file attached to a deal is
 *    kept with the deal; otherwise with the agent.
 * 2. **Review** in a queued job, in steps (read pages, pull terms and
 *    dates, check common problems, compare with the earlier version),
 *    then "Your contract review is ready".
 * 3. **Result:** key terms with what changed, findings ranked High and
 *    Check with pages, deadlines ready for the calendar. 20 credits, only
 *    once the review is done. Not legal advice.
 * 4. **Then:** add the deadlines to the deal (or to-dos and the calendar),
 *    send the summary to the broker (a virtual broker question), or ask
 *    about the contract (answers cite pages, with a note for the client).
 *
 * @phpstan-import-type Finding from ContractChecks
 * @phpstan-import-type Deadline from ContractChecks
 *
 * @phpstan-type TermRow array{code: string, label: string, value: string, page: int|null, previous: string|null, changed: bool}
 * @phpstan-type ReviewRow array{id: string, file_name: string, form: string|null, form_name: string|null, status: 'reviewing'|'done'|'failed', step: 'read'|'terms'|'checks'|'compare'|'done', pages: int, created_at: string|null, reviewed_at: string|null, deal: array{id: int, uuid: string, property: string}|null, compared: bool, terms: list<TermRow>, addenda: list<string>, findings: list<Finding>, deadlines: list<Deadline>, deadlines_added: bool, broker_question: string|null, questions: list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>, credits: int, cost: int, error: string|null}
 */
final readonly class ContractReviews
{
    public const FEATURE = 'contract_review';

    public const ASK_FEATURE = 'contract_question';

    public const MAX_KB = 20 * 1024;

    public function __construct(
        private TextExtractor $extractor,
        private Bedrock $bedrock,
        private AiCredits $credits,
        private AiSettings $settings,
        private StateRules $rules,
        private Deals $deals,
        private Todos $todos,
        private VirtualBroker $broker,
        private Memberships $memberships,
        private Application $app,
    ) {}

    /**
     * Start a review: a file the agent chose, a deal's document, or the sample.
     *
     * @throws ValidationException
     */
    public function start(User $agent, ?UploadedFile $file, ?string $dealFileUuid, ?int $dealId, bool $sample): ContractReview
    {
        $cost = $this->cost();

        if (! $this->credits->canSpend($agent, $cost)) {
            throw ValidationException::withMessages(['file' => self::line('contracts.errors.credits', ['credits' => (string) $cost])]);
        }

        $deal = $dealId === null ? null : Deal::query()->where('user_id', $agent->id)->find($dealId);

        if ($dealId !== null && $deal === null) {
            throw ValidationException::withMessages(['deal_id' => self::line('contracts.errors.deal')]);
        }

        $uuid = (string) Str::uuid7();
        $dealFile = null;

        if ($dealFileUuid !== null) {
            $dealFile = DealFile::query()->where('user_id', $agent->id)->where('uuid', $dealFileUuid)->with('deal')->first()
                ?? throw ValidationException::withMessages(['deal_file' => self::line('contracts.errors.deal')]);
            $deal = $dealFile->deal;
            [$name, $path, $mime] = [$dealFile->name, $dealFile->path, 'application/pdf'];
        } elseif ($sample) {
            abort_if($this->app->isProduction(), 404);
            $path = "contracts/{$agent->id}/{$uuid}.pdf";
            LessonFiles::disk()->put($path, SampleContract::pdf());
            [$name, $mime] = ['Sample TREC 20-18.pdf', 'application/pdf'];
        } elseif ($file !== null) {
            $mime = (string) $file->getMimeType();
            $name = Str::limit($file->getClientOriginalName(), 200, '');

            if ($deal !== null && $mime === 'application/pdf') {
                $dealFile = DealFiles::upload($deal, $file);
                $path = $dealFile->path;
            } else {
                $path = "contracts/{$agent->id}/{$uuid}.".($mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg'));
                LessonFiles::disk()->put($path, (string) $file->getContent());
            }
        } else {
            throw ValidationException::withMessages(['file' => self::line('contracts.errors.nothing')]);
        }

        $review = ContractReview::query()->create([
            'uuid' => $uuid, 'user_id' => $agent->id, 'organisation_id' => $this->brokerageId($agent), 'deal_id' => $deal?->id, 'deal_file_id' => $dealFile?->id,
            'file_name' => $name, 'path' => $path, 'mime' => $mime, 'status' => 'reviewing', 'step' => 'read', 'language' => $agent->locale->value,
        ]);
        ReviewContract::dispatch($review->id)->afterCommit();

        return $review->refresh();
    }

    /** The review itself (the queued job): read, pull terms, check, compare; charged once done. */
    public function run(ContractReview $review): void
    {
        if ($review->status !== 'reviewing') {
            return;
        }

        $agent = $review->user;
        $state = 'TX';

        try {
            $bytes = (string) LessonFiles::disk()->get($review->path);
            $pages = $this->pages($review, $bytes);
            $review->forceFill(['step' => 'terms', 'pages' => max(count($pages), 1)])->save();

            $engine = 'simulator';
            $reading = $this->reading($pages, $bytes, $review, $state, $engine);
            $review->forceFill(['step' => 'checks'])->save();

            $earlier = $this->earlier($review, $reading);
            $review->forceFill(['step' => 'compare'])->save();

            [$findings, $deadlines, $effective] = ContractChecks::run($reading, $this->rules->inForce($state, CarbonImmutable::now()), $earlier === null ? null : array_map(static fn (array $t): string => $t['value'], $earlier->terms ?? []), $review->language);
            $terms = [];

            foreach ($reading->terms as $code => $term) {
                $terms[$code] = [...$term, 'previous' => $earlier === null ? null : ($earlier->terms[$code]['value'] ?? null)];
            }

            $cost = $this->cost();
            $review->forceFill([
                'status' => 'done', 'step' => 'done', 'form' => $reading->form, 'page_text' => $pages, 'terms' => $terms, 'addenda' => $reading->addenda,
                'findings' => $findings, 'deadlines' => $deadlines, 'effective_on' => $effective, 'engine' => $engine, 'credits' => $cost,
                'previous_id' => $earlier?->id, 'reviewed_at' => now(), 'error' => null,
            ])->save();
            $this->credits->charge($agent, self::FEATURE, $engine, $cost);
        } catch (Throwable $e) {
            Log::warning('contract_review.failed', ['review' => $review->uuid, 'error' => $e->getMessage()]);
            $review->forceFill(['status' => 'failed', 'step' => 'done', 'error' => $e instanceof RuntimeException && str_starts_with($e->getMessage(), 'contracts.') ? $e->getMessage() : 'contracts.errors.unreadable'])->save();
        }

        $agent->notify(new ContractReviewReady($review));
    }

    /** The review's deadlines on the deal (key dates, with to-dos), or as to-dos on the calendar. */
    public function addDeadlines(ContractReview $review): ContractReview
    {
        if ($review->status !== 'done' || $review->deadlines_added_at !== null || ($review->deadlines ?? []) === []) {
            return $review;
        }

        $deal = $review->deal_id === null ? null : Deal::query()->find($review->deal_id);

        foreach ($review->deadlines ?? [] as $d) {
            if ($deal !== null) {
                $this->deals->addDate($deal, $d['kind'], $d['kind'] === 'other' ? $d['label'] : null, $d['due_on']);
            } else {
                $this->todos->create($review->user, ['title' => mb_substr($d['label'].' · '.$this->property($review), 0, 200), 'due_date' => $d['due_on'], 'source' => 'contract'], Uuid::uuid5(Uuid::NAMESPACE_URL, "reos:contract:{$review->uuid}:{$d['code']}")->toString());
            }
        }

        $review->forceFill(['deadlines_added_at' => now()])->save();

        return $review;
    }

    /** The summary to the broker, as a virtual broker question they confirm or correct. */
    public function sendToBroker(ContractReview $review): ContractReview
    {
        if ($review->status !== 'done' || $review->broker_question_id !== null) {
            return $review;
        }

        $language = $review->language;
        $summary = [];

        foreach ($review->findings ?? [] as $f) {
            $summary[] = '• '.self::line('contracts.severity.'.$f['severity'], [], $language).': '.$f['title'].($f['page'] !== null ? ' ('.self::line('contracts.page', ['page' => (string) $f['page']], $language).')' : '');
        }

        foreach ($review->deadlines ?? [] as $d) {
            $summary[] = '• '.$d['label'].': '.$d['due_on'];
        }

        $question = self::line('contracts.broker.question', ['form' => $this->formName($review) ?? $review->file_name, 'property' => $this->property($review)], $language);
        $sources = [['kind' => 'manual', 'title' => $review->file_name, 'citation' => null, 'page' => null, 'url' => null, 'effective_from' => $review->reviewed_at?->toDateString(), 'excerpt' => self::line('contracts.broker.source', [], $language)]];
        $asked = $this->broker->refer($review->user, $question, implode("\n", $summary), $sources, $review->deal_id, $review->engine);
        $review->forceFill(['broker_question_id' => $asked->id])->save();

        return $review;
    }

    /**
     * Ask about this contract (screen 66): an answer that cites pages, and a
     * note the agent can send the client. 1 credit.
     *
     * @throws ValidationException
     */
    public function ask(ContractReview $review, string $question): ContractReview
    {
        $cost = max(0, $this->settings->cost('ai_suggestion'));

        if ($review->status !== 'done') {
            throw ValidationException::withMessages(['question' => self::line('contracts.errors.notReady')]);
        }

        if (! $this->credits->canSpend($review->user, $cost)) {
            throw ValidationException::withMessages(['question' => self::line('contracts.errors.credits', ['credits' => (string) $cost])]);
        }

        $language = $review->language;
        $words = PolicySearch::terms($question);
        $scored = [];

        foreach ($review->page_text ?? [] as $page => $text) {
            $score = PolicySearch::score($words, $text);
            if ($score > 0) {
                $scored[(int) $page] = $score;
            }
        }

        arsort($scored);
        $best = $scored === [] ? 0.0 : max($scored);
        // The best page, and a second one only if it's nearly as good.
        $pages = array_slice(array_keys(array_filter($scored, static fn (float $s): bool => $s >= $best * 0.8)), 0, 2);
        $bedrock = $this->bedrock->client(30);
        $answer = null;
        $engine = 'simulator';

        if ($bedrock !== null && $pages !== []) {
            try {
                $answer = (new ClaudeContractAnswerer($bedrock['client'], $bedrock['model']))->answer($question, array_intersect_key($review->page_text ?? [], array_flip($pages)), $language);
                $engine = 'ai';
            } catch (Throwable $e) {
                Log::warning('contract_review.ask_failed', ['error' => $e->getMessage()]);
            }
        }

        if ($answer === null) {
            $answer = $pages === []
                ? self::line('contracts.ask.none', [], $language)
                : implode("\n\n", array_map(fn (int $p): string => self::line('contracts.ask.page', ['page' => (string) $p], $language).' '.self::sentence((string) ($review->page_text[$p] ?? ''), $words), $pages));
        }

        $buyer = $review->terms['buyer']['value'] ?? null;
        $note = self::line('contracts.ask.note', ['name' => $buyer !== null ? Str::before($buyer, ' and ') : self::line('contracts.ask.there', [], $language), 'answer' => $answer], $language);
        $review->forceFill(['questions' => [...($review->questions ?? []), ['question' => trim($question), 'answer' => $answer, 'pages' => $pages, 'note' => $note, 'asked_at' => now()->toIso8601String()]]])->save();
        $this->credits->charge($review->user, self::ASK_FEATURE, $engine, $cost);

        return $review;
    }

    /** @return ReviewRow */
    public function row(ContractReview $review): array
    {
        $terms = [];

        foreach ($review->terms ?? [] as $code => $t) {
            $previous = $t['previous'] ?? null;
            $terms[] = [
                'code' => (string) $code, 'label' => self::line("contracts.terms.{$code}"), 'value' => $t['value'], 'page' => $t['page'],
                'previous' => $previous, 'changed' => $review->previous_id !== null && $previous !== $t['value'],
            ];
        }

        return [
            'id' => $review->uuid, 'file_name' => $review->file_name, 'form' => $review->form, 'form_name' => $this->formName($review),
            'status' => match ($review->status) {
                'done' => 'done', 'failed' => 'failed', default => 'reviewing'
            },
            'step' => match ($review->step) {
                'terms' => 'terms', 'checks' => 'checks', 'compare' => 'compare', 'done' => 'done', default => 'read'
            },
            'pages' => $review->pages, 'created_at' => $review->created_at?->toIso8601String(), 'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            'deal' => $review->deal === null ? null : ['id' => $review->deal->id, 'uuid' => $review->deal->uuid, 'property' => $review->deal->property],
            'compared' => $review->previous_id !== null, 'terms' => $terms, 'addenda' => $review->addenda ?? [],
            'findings' => $review->findings ?? [], 'deadlines' => $review->deadlines ?? [], 'deadlines_added' => $review->deadlines_added_at !== null,
            'broker_question' => $review->brokerQuestion?->uuid, 'questions' => $review->questions ?? [], 'credits' => $review->credits, 'cost' => $this->cost(),
            'error' => $review->error === null ? null : self::line($review->error),
        ];
    }

    /**
     * The agent's reviews, newest first, and what a review costs.
     *
     * @return array{reviews: list<ReviewRow>, cost: int, credits_left: int, sample: bool, files: list<array{uuid: string, name: string, deal: string, added_at: string|null}>}
     */
    public function overview(User $agent): array
    {
        $reviews = [];

        foreach (ContractReview::query()->where('user_id', $agent->id)->with(['deal:id,uuid,property', 'brokerQuestion:id,uuid'])->latest('id')->limit(30)->get() as $review) {
            $reviews[] = $this->row($review);
        }

        return [
            'reviews' => $reviews,
            'cost' => $this->cost(),
            'credits_left' => $this->credits->left($agent),
            'sample' => ! $this->app->isProduction(),
            // Documents in the agent's deals (from REOS or a transaction tool).
            'files' => array_values(DealFile::query()->where('user_id', $agent->id)->where('scan_status', '!=', 'infected')->with('deal:id,property')->latest('id')->limit(20)->get()
                ->map(static fn (DealFile $f): array => ['uuid' => $f->uuid, 'name' => $f->name, 'deal' => $f->deal->property, 'added_at' => $f->created_at?->toIso8601String()])->all()),
        ];
    }

    public function own(User $agent, string $uuid): ContractReview
    {
        return ContractReview::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with(['deal:id,uuid,property', 'brokerQuestion:id,uuid', 'user'])->firstOrFail();
    }

    public function cost(): int
    {
        return max(0, $this->settings->cost(self::FEATURE));
    }

    /**
     * The pages' words: the PDF's text; a scan has none (Claude reads it).
     *
     * @return array<int, string>
     */
    private function pages(ContractReview $review, string $bytes): array
    {
        if ($review->mime !== 'application/pdf') {
            return [];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'contract');

        if ($tmp === false) {
            return [];
        }

        try {
            file_put_contents($tmp, $bytes);
            $pages = [];

            foreach ($this->extractor->extract($tmp, 'pdf') as $section) {
                $pages[$section->page ?? count($pages) + 1] = $section->text;
            }

            return $pages;
        } finally {
            @unlink($tmp);
        }
    }

    /** @param  array<int, string>  $pages */
    private function reading(array $pages, string $bytes, ContractReview $review, string $state, string &$engine): ContractReading
    {
        $engine = 'simulator';
        $rules = TrecForms::read($pages);
        $bedrock = $this->bedrock->client(60);

        if ($bedrock !== null) {
            try {
                $claude = (new ClaudeContractReader($bedrock['client'], $bedrock['model']))->read($pages, $pages === [] ? $bytes : null, $review->mime, $state, $review->language);
                $engine = 'ai';

                // REOS's own reading of a known form wins; Claude fills the gaps and adds its findings.
                return new ContractReading($rules->form ?? $claude->form, [...$claude->terms, ...$rules->terms], array_values(array_unique([...$rules->addenda, ...$claude->addenda])), $claude->findings);
            } catch (Throwable $e) {
                Log::warning('contract_review.reader_failed', ['error' => $e->getMessage()]);
            }
        }

        if ($pages === [] || trim(implode('', $pages)) === '') {
            throw new RuntimeException($review->mime === 'application/pdf' ? 'contracts.errors.noText' : 'contracts.errors.photos');
        }

        return $rules;
    }

    /** The earlier version: the latest done review of the same deal, or of the same property. */
    private function earlier(ContractReview $review, ContractReading $reading): ?ContractReview
    {
        $property = $reading->value('property');
        $query = ContractReview::query()->where('user_id', $review->user_id)->where('status', 'done')->where('id', '<', $review->id)->latest('id');

        if ($review->deal_id !== null) {
            return $query->where('deal_id', $review->deal_id)->first();
        }

        return $property === null ? null : $query->get()->first(static fn (ContractReview $r): bool => ($r->terms['property']['value'] ?? null) === $property);
    }

    private function property(ContractReview $review): string
    {
        return $review->deal->property ?? ($review->terms['property']['value'] ?? $review->file_name);
    }

    private function formName(ContractReview $review): ?string
    {
        return $review->form === null ? null : self::line('contracts.forms.'.$review->form, [], $review->language);
    }

    private function brokerageId(User $agent): ?int
    {
        foreach ($this->memberships->of($agent) as $membership) {
            if ($membership->role === Role::Agent && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation->id;
            }
        }

        return null;
    }

    /**
     * The sentence on a page holding most of the question's words.
     *
     * @param  list<string>  $words
     */
    private static function sentence(string $text, array $words): string
    {
        $best = '';
        $score = -1.0;

        foreach (preg_split('/(?<=[.!?])\s+/u', trim((string) preg_replace('/\s+/u', ' ', $text))) ?: [] as $sentence) {
            $s = PolicySearch::score($words, $sentence);
            if ($s > $score) {
                [$best, $score] = [$sentence, $s];
            }
        }

        return PolicySearch::excerpt($best, 400);
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = [], ?string $language = null): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
