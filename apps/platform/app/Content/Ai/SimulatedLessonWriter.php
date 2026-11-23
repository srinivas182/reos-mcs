<?php

declare(strict_types=1);

namespace App\Content\Ai;

use App\Content\Builder\CourseBuilder;
use App\Enums\QuizTopic;

/**
 * The lesson writer when Claude isn't set up (demos, tests, the preview):
 * the lesson is the sources' own sentences in order, each citing its
 * passage; check-ins, questions and flashcards are built from those lines,
 * and the "Spanish" is the English marked [ES]. Never shown as real AI.
 *
 * @phpstan-import-type Brief from LessonWriter
 * @phpstan-import-type Draft from LessonWriter
 */
final class SimulatedLessonWriter implements LessonWriter
{
    /** Wrong answers for the simulator's questions. */
    private const DISTRACTORS = [
        'The sources do not cover this.',
        'Only the broker decides this, without any written agreement.',
        'This applies only outside Texas.',
        'It is optional and has no deadline.',
    ];

    public function engine(): string
    {
        return 'simulated';
    }

    public function write(array $brief): array
    {
        $budget = max(1, $brief['minutes']) * CourseBuilder::WORDS_PER_MINUTE;
        $every = max(1, $brief['checkin_minutes']) * CourseBuilder::WORDS_PER_MINUTE;
        $lines = [];
        $checkins = [];
        $words = 0;
        $sinceCheckin = 0;

        foreach ($brief['passages'] as $n => $passage) {
            foreach (self::sentences($passage['text']) as $sentence) {
                if ($words >= $budget) {
                    break 2;
                }

                $lines[] = ['text' => $sentence, 'source' => $n];
                $count = str_word_count($sentence);
                $words += $count;
                $sinceCheckin += $count;

                if ($sinceCheckin >= $every) {
                    $checkins[] = ['after_line' => count($lines) - 1, 'prompt' => 'Still with us? Tap Continue to keep your seat time.'];
                    $sinceCheckin = 0;
                }
            }
        }

        $questions = [];
        $flashcards = [];

        foreach ($lines as $i => $line) {
            if (count($questions) < CourseBuilder::QUESTIONS_PER_LESSON && $i % 2 === 0) {
                $options = [mb_substr($line['text'], 0, 200), ...array_slice(self::DISTRACTORS, 0, 3)];
                $correct = $i % 4 === 0 ? 0 : 1;
                [$options[0], $options[$correct]] = [$options[$correct], $options[0]];
                $questions[] = [
                    'prompt' => 'Which statement matches the lesson?',
                    'options' => $options,
                    'correct' => $correct,
                    'explanation' => 'It is what the source says.',
                    'source' => $line['source'],
                    'topic' => QuizTopic::StateRules->value,
                ];
            }

            if (count($flashcards) < CourseBuilder::FLASHCARDS_PER_LESSON) {
                $parts = explode(' ', $line['text']);
                $flashcards[] = ['front' => implode(' ', array_slice($parts, 0, 6)).(count($parts) > 6 ? ' …' : ''), 'back' => $line['text'], 'source' => $line['source']];
            }
        }

        return Drafts::clean(['title' => $brief['title'], 'lines' => $lines, 'checkins' => $checkins, 'questions' => $questions, 'flashcards' => $flashcards], count($brief['passages']), $brief['title']);
    }

    public function translate(array $texts): array
    {
        return array_map(static fn (string $text): string => '[ES] '.$text, $texts);
    }

    /** @return list<string> */
    private static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim(preg_replace('/\s+/u', ' ', $text) ?? ''));

        return array_values(array_filter(array_map(trim(...), $parts === false ? [] : $parts), static fn (string $s): bool => str_word_count($s) >= 3));
    }
}
