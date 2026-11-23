<?php

declare(strict_types=1);

namespace App\Content\Review;

use App\Models\Course;
use App\Models\KbPassage;
use Illuminate\Support\Str;

/**
 * Checks a course before it goes live (A5.3b; screen 63): an approval number
 * in every state for CE and qualifying courses, and no legal flag left
 * open in any lesson's text, slides or video transcript.
 */
final class ContentChecks
{
    /**
     * @return array{
     *     approvals: list<string>,
     *     flags: list<array{key: string, rule: string, lesson_id: int, lesson_title: string, kind: string, heading: string|null, page: int|null, seconds: int|null, excerpt: string, cleared: bool}>,
     *     open: int,
     *     clean: bool
     * }
     */
    public function for(Course $course): array
    {
        $cleared = array_column($course->cleared_flags ?? [], 'key');
        $approved = $course->missingApprovals() === [] && $course->approvalNumbers() !== [];
        $flags = [];

        $passages = KbPassage::query()
            ->with('lesson:id,title')
            ->join('lessons', 'lessons.id', '=', 'kb_passages.lesson_id')
            ->whereNull('lessons.deleted_at')
            ->where('kb_passages.course_id', $course->id)
            ->orderBy('lessons.position')->orderBy('kb_passages.position')
            ->get(['kb_passages.*']);

        foreach ($passages as $passage) {
            foreach (LegalRule::cases() as $rule) {
                if ($rule->waivedByApproval() && $approved) {
                    continue;
                }

                $match = self::match($rule, $passage->content);

                if ($match === null) {
                    continue;
                }

                $key = $passage->content_hash.':'.$rule->value;
                $flags[] = [
                    'key' => $key,
                    'rule' => $rule->value,
                    'lesson_id' => $passage->lesson_id,
                    'lesson_title' => $passage->lesson->title,
                    'kind' => $passage->kind,
                    'heading' => $passage->heading,
                    'page' => $passage->page,
                    'seconds' => $passage->seconds,
                    'excerpt' => self::excerpt($passage->content, $match),
                    'cleared' => in_array($key, $cleared, true),
                ];
            }
        }

        $open = count(array_filter($flags, static fn (array $flag): bool => ! $flag['cleared']));
        $approvals = $course->missingApprovals();

        return ['approvals' => $approvals, 'flags' => $flags, 'open' => $open, 'clean' => $open === 0 && $approvals === []];
    }

    /** @return array{0: int, 1: int}|null offset and length of the first match */
    private static function match(LegalRule $rule, string $text): ?array
    {
        foreach ($rule->patterns() as $pattern) {
            if (preg_match($pattern, $text, $found, PREG_OFFSET_CAPTURE) === 1) {
                return [mb_strlen(substr($text, 0, $found[0][1])), mb_strlen($found[0][0])];
            }
        }

        return null;
    }

    /**
     * The flagged words in [[ ]] with some context.
     *
     * @param  array{0: int, 1: int}  $match
     */
    private static function excerpt(string $text, array $match): string
    {
        [$at, $length] = $match;
        $start = max(0, $at - 70);
        $before = mb_substr($text, $start, $at - $start);
        $words = mb_substr($text, $at, $length);
        $after = mb_substr($text, $at + $length, 90);

        return ($start > 0 ? '… ' : '').$before.'[['.$words.']]'.Str::limit($after, 90, ' …');
    }
}
