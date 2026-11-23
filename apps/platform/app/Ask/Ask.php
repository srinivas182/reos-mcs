<?php

declare(strict_types=1);

namespace App\Ask;

use App\Content\Knowledge\KnowledgeSearch;
use App\Content\Knowledge\SearchHit;
use App\Content\Learning\Learning;
use App\Content\Player\SimulatedInstructor;
use App\Models\AskMessage;
use App\Models\AskThread;
use App\Models\KbPassage;
use App\Models\User;
use App\Practice\MathLab\MathLab;
use App\Services\Ai\AiCredits;
use App\Services\Ai\Bedrock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Ask, the study mate (A10.1, candidate screen 13, f02, k11; ADR 0039).
 *
 * The answer order (decision 1):
 * 1. **Course content:** the knowledge base across every live course in the
 *    person's state, with the lesson and second to watch. Free.
 * 2. **Beyond the course:** a general answer from Claude only when someone
 *    pays for it: a candidate's sponsor (school, brokerage or association)
 *    from its own credit pool, or an agent's own credits.
 * 3. **Otherwise:** say so plainly and offer their instructor (A10.1b) or a
 *    session (A10.2). A candidate never sees a credit balance or a price.
 *
 * @phpstan-type Source array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}
 * @phpstan-type Quiz array{prompt: string, options: list<string>, correct: int, explanation: string}
 * @phpstan-type Reply array{answer: string, sources: list<Source>, quiz: Quiz|null, covered: bool}
 * @phpstan-type MessageRow array{id: int, role: string, body: string, tier: string|null, mode: string|null, sources: list<Source>, quiz: Quiz|null, math_kind: string|null, sponsor: string|null, created_at: string|null}
 * @phpstan-type ThreadRow array{uuid: string, title: string, updated_at: string|null, messages: list<MessageRow>}
 */
final readonly class Ask
{
    public const FEATURE = 'ask';

    public const GENERAL_FEATURE = 'ask_general';

    public const MODES = ['ask', 'simpler', 'spanish', 'quiz'];

    /** Question words that say nothing about the topic. */
    private const STOP = ['what', 'when', 'where', 'which', 'who', 'why', 'how', 'does', 'the', 'and', 'for', 'can', 'are', 'you', 'que', 'qué', 'cuándo', 'cuando', 'cómo', 'como', 'por', 'para', 'una', 'los', 'las', 'del'];

    public function __construct(private KnowledgeSearch $search, private Bedrock $bedrock, private AiCredits $credits, private Learning $learning) {}

    /**
     * A new question (mode `ask`), or a follow-up on the thread's last
     * answer: simpler, in Spanish, or quiz me.
     *
     * @throws ValidationException
     */
    public function ask(User $user, string $question, string $mode, ?AskThread $thread): AskThread
    {
        $previous = null;

        if ($mode !== 'ask') {
            $last = $thread?->messages()->where('role', 'question')->latest('id')->first();
            $answer = $thread?->messages()->where('role', 'answer')->latest('id')->first();
            if ($thread === null || $last === null) {
                throw ValidationException::withMessages(['mode' => __('ask.errors.nothingYet')]);
            }
            $question = $last->body;
            $previous = $answer?->body;
        }

        if (! $this->credits->withinHourlyLimit($user)) {
            throw new HttpException(429, __('crm.insight.limit'));
        }

        $state = $this->learning->state($user);
        $language = $mode === 'spanish' ? 'es' : $user->locale->value;
        $thread ??= AskThread::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'title' => Str::limit(trim($question), 117), 'state_code' => $state]);

        if ($mode === 'ask') {
            AskMessage::query()->create(['ask_thread_id' => $thread->id, 'role' => 'question', 'body' => $question]);
        }

        [$reply, $tier, $sponsor] = $this->reply($user, $question, $mode, $previous, $state, $language);

        DB::transaction(function () use ($thread, $reply, $tier, $sponsor, $mode, $question): void {
            AskMessage::query()->create([
                'ask_thread_id' => $thread->id, 'role' => 'answer', 'body' => $reply['answer'], 'tier' => $tier, 'mode' => $mode,
                'sources' => $reply['sources'], 'quiz' => $reply['quiz'], 'math_kind' => MathLab::kindForText($question), 'sponsor' => $sponsor,
            ]);
            $thread->touch();
        });

        return $thread->refresh();
    }

    /**
     * Content first; beyond it only if someone pays; otherwise a plain "not
     * in your course" with the best passage there is.
     *
     * @return array{0: Reply, 1: string, 2: string|null} the reply, its tier and who paid
     */
    private function reply(User $user, string $question, string $mode, ?string $previous, string $state, string $language): array
    {
        $words = array_filter(KnowledgeSearch::words($question), static fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true));
        $passages = $this->passages($this->search->search($words === [] ? $question : implode(' or ', $words), [$state], null, 5, true));
        $bedrock = $this->bedrock->client(30);

        if ($passages !== []) {
            $reply = null;
            if ($bedrock !== null) {
                try {
                    $reply = (new ClaudeTutor($bedrock['client'], $bedrock['model']))->answer($question, $passages, $mode, $previous, $language, $state);
                } catch (Throwable $e) {
                    Log::warning('ask.failed', ['error' => $e->getMessage()]);
                }
            }
            $reply ??= self::fromSimulator($passages, $mode, $previous, $language);
            $this->credits->logFree($user, self::FEATURE, $bedrock === null ? 'simulator' : 'ai');

            if ($reply['covered']) {
                return [$reply, 'content', null];
            }
        }

        // Beyond the course: only when someone pays (never the candidate).
        $candidate = $this->credits->forCandidate($user);
        $sponsor = $candidate ? $this->credits->sponsorFor($user) : null;
        $pays = $candidate ? $sponsor !== null : $this->credits->canSpend($user);

        if ($pays) {
            if ($bedrock === null) {
                $this->credits->logFree($user, self::GENERAL_FEATURE, 'simulator');

                return [['answer' => self::text('ask.sim.general', ['state' => $state], $language), 'sources' => [], 'quiz' => null, 'covered' => true], 'sponsored', $sponsor?->name];
            }

            try {
                $reply = (new ClaudeTutor($bedrock['client'], $bedrock['model']))->answer($question, [], $mode, $previous, $language, $state);
                $this->credits->charge($user, self::GENERAL_FEATURE, 'ai');

                return [$reply, 'sponsored', $sponsor?->name];
            } catch (Throwable $e) {
                Log::warning('ask.general_failed', ['error' => $e->getMessage()]);
            }
        }

        return [['answer' => self::text('ask.limited', ['state' => $state], $language), 'sources' => array_slice($passages, 0, 1), 'quiz' => null, 'covered' => false], 'limited', null];
    }

    /**
     * The demo study mate (no Bedrock keys): the best passage, word for word.
     *
     * @param  list<Source>  $passages
     * @return Reply
     */
    private static function fromSimulator(array $passages, string $mode, ?string $previous, string $language): array
    {
        $answer = SimulatedInstructor::answer($passages, $mode, $previous, $language);

        return ['answer' => $answer['answer'], 'sources' => $answer['sources'], 'quiz' => $answer['quiz'], 'covered' => true];
    }

    /**
     * @param  list<SearchHit>  $hits
     * @return list<Source>
     */
    private function passages(array $hits): array
    {
        $rows = KbPassage::query()->whereIn('id', array_map(static fn (SearchHit $h): int => $h->passageId, $hits))->get()->keyBy('id');
        $list = [];

        foreach ($hits as $hit) {
            $row = $rows->get($hit->passageId);
            if ($row instanceof KbPassage) {
                $list[] = ['lesson_id' => $hit->lessonId, 'lesson' => $hit->lessonTitle, 'seconds' => $hit->seconds, 'page' => $hit->page, 'heading' => $hit->heading, 'text' => mb_substr(trim($row->content), 0, 1200)];
            }
        }

        return $list;
    }

    /** @return Quiz|null */
    public static function quiz(mixed $quiz): ?array
    {
        if (! is_array($quiz) || ! is_string($quiz['prompt'] ?? null) || ! is_array($quiz['options'] ?? null) || ! is_int($quiz['correct'] ?? null)) {
            return null;
        }

        $options = array_values(array_filter(array_map(static fn (mixed $o): ?string => is_string($o) ? mb_substr(trim($o), 0, 300) : null, $quiz['options']), static fn (?string $o): bool => $o !== null && $o !== ''));

        if (count($options) < 2 || ! isset($options[$quiz['correct']])) {
            return null;
        }

        return ['prompt' => mb_substr($quiz['prompt'], 0, 400), 'options' => array_slice($options, 0, 4), 'correct' => $quiz['correct'] < 4 ? $quiz['correct'] : 0, 'explanation' => is_string($quiz['explanation'] ?? null) ? mb_substr($quiz['explanation'], 0, 600) : ''];
    }

    /** @return ThreadRow */
    public static function thread(AskThread $thread): array
    {
        return [
            'uuid' => $thread->uuid, 'title' => $thread->title, 'updated_at' => $thread->updated_at?->toIso8601String(),
            'messages' => array_values($thread->messages()->get()->map(self::message(...))->all()),
        ];
    }

    /** @return MessageRow */
    public static function message(AskMessage $m): array
    {
        return [
            'id' => $m->id, 'role' => $m->role, 'body' => $m->body, 'tier' => $m->tier, 'mode' => $m->mode, 'sources' => $m->sources ?? [], 'quiz' => $m->quiz,
            'math_kind' => $m->math_kind, 'sponsor' => $m->sponsor, 'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, string>  $replace */
    private static function text(string $key, array $replace, string $language): string
    {
        $text = __($key, $replace, $language);

        return is_string($text) ? $text : $key;
    }
}
