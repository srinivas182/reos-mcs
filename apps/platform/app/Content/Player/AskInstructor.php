<?php

declare(strict_types=1);

namespace App\Content\Player;

use App\Content\Knowledge\KnowledgeSearch;
use App\Content\Knowledge\SearchHit;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\Bedrock;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Interrupt and ask (candidate screen 9, agent screen 94, Sprint 8 wrap-up):
 * the learner asks mid-lesson, the video pauses at the second, and the
 * instructor answers from this course only, citing where. Then: explain it
 * simpler, in Spanish, or quiz me. With no passage to go on, it says so
 * rather than guess. Free for every learner (decided 23 November 2026):
 * logged at 0 credits and held back by the hourly AI limit (429).
 *
 * @phpstan-type Source array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}
 * @phpstan-type Quiz array{prompt: string, options: list<string>, correct: int, explanation: string}
 * @phpstan-type Answer array{answer: string, sources: list<Source>, quiz: Quiz|null, engine: string, found: bool}
 */
final readonly class AskInstructor
{
    public const FEATURE = 'lesson_ask';

    public const MODES = ['ask', 'simpler', 'spanish', 'quiz'];

    /** Question words that say nothing about the topic. */
    private const STOP = ['what', 'when', 'where', 'which', 'who', 'why', 'how', 'does', 'the', 'and', 'for', 'can', 'are', 'you', 'que', 'qué', 'cuándo', 'cuando', 'cómo', 'como', 'por', 'para', 'una', 'los', 'las', 'del'];

    public function __construct(private KnowledgeSearch $search, private Bedrock $bedrock, private AiCredits $credits) {}

    /** @return Answer */
    public function ask(User $learner, Lesson $lesson, string $question, string $mode, ?string $previous, string $state): array
    {
        $language = $mode === 'spanish' ? 'es' : $learner->locale->value;
        // A question isn't a search: any of its words may match (best match first), and meaning search does the rest.
        $words = array_filter(KnowledgeSearch::words($question), static fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true));
        $hits = $this->search->search($words === [] ? $question : implode(' or ', $words), [$state], null, 5, true, $lesson->course_id);
        $passages = $this->passages($hits);

        if ($passages === []) {
            return ['answer' => __('learn.ask.notFound', [], $language), 'sources' => [], 'quiz' => null, 'engine' => 'none', 'found' => false];
        }

        $bedrock = $this->bedrock->client(30);

        if ($bedrock === null) {
            $this->credits->logFree($learner, self::FEATURE, 'simulator');

            return SimulatedInstructor::answer($passages, $mode, $previous, $language);
        }

        if (! $this->credits->withinHourlyLimit($learner)) {
            throw new HttpException(429, __('crm.insight.limit'));
        }

        try {
            $answer = (new ClaudeInstructor($bedrock['client'], $bedrock['model']))->answer($question, $passages, $mode, $previous, $language, $lesson->title);
        } catch (Throwable $e) {
            Log::warning('learn.ask.failed', ['lesson' => $lesson->id, 'error' => $e->getMessage()]);

            return SimulatedInstructor::answer($passages, $mode, $previous, $language);
        }

        $this->credits->logFree($learner, self::FEATURE, 'ai');

        return $answer;
    }

    /**
     * The passages behind the hits, in full (cut to 1,200 characters).
     *
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
}
