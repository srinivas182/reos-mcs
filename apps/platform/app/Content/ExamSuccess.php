<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Ai\LessonPipeline;
use App\Content\Builder\CourseBuilder;
use App\Content\Builder\LessonReview;
use App\Content\Builder\SourceRole;
use App\Content\Review\ContentChecks;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVersion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\LessonFiles;
use Illuminate\Support\Facades\DB;

/**
 * The Exam success course (A9.2f, candidate screen 23; Sprint 9 decision 5).
 *
 * REOS writes eight short scripts in English and Spanish (`database/data/exam_success.php`):
 * how the exam is built, timing, NOT/EXCEPT/BEST questions, eliminating
 * wrong answers, math shortcuts, Texas portion traps, the last 7 days and
 * exam-day nerves.
 *
 * `install` puts them through the AI lesson pipeline's later steps (A7.3).
 * - The course is REOS's, a test prep course, so every candidate package
 *   with `learn.test_prep` includes it.
 * - Each script is a source on the course's module.
 * - Each lesson gets a version whose lines are the script, line by line,
 *   with the legal-line checks run in both languages, plus two practice
 *   questions for review.
 * - Clean lessons go to the video vendor; nothing reaches learners until
 *   a reviewer approves and publishes each lesson on screen 44.
 *
 * The reviewers are the people assigned as the course's instructors (Nelle).
 * Installing again adds what's missing and leaves lessons already there,
 * in review or approved, as they are.
 *
 * @phpstan-type Script array{key: string, title: array{en: string, es: string}, sections: list<array{heading: array{en: string, es: string}, lines: list<array{en: string, es: string}>}>, questions: list<array{topic: string, en: array{prompt: string, options: list<string>, explanation: string}, es: array{prompt: string, options: list<string>, explanation: string}, correct: int}>}
 */
final readonly class ExamSuccess
{
    public const SLUG = 'exam-success-texas';

    public const MODULE = 'Exam success';

    /** Speaking pace for a lesson's length, as in the course builder. */
    private const WORDS_PER_MINUTE = 130;

    public function __construct(private LessonPipeline $pipeline, private CourseBuilder $builder, private AuditLogger $audit) {}

    /** @return list<Script> */
    public static function scripts(): array
    {
        /** @var list<Script> $scripts */
        $scripts = require database_path('data/exam_success.php');

        return $scripts;
    }

    public static function course(): ?Course
    {
        return Course::query()->where('slug', self::SLUG)->first();
    }

    /**
     * Installs the course for review; `reviewers` become its instructors.
     *
     * @param  list<User>  $reviewers
     */
    public function install(array $reviewers = [], ?User $actor = null): Course
    {
        $scripts = self::scripts();
        $minutes = array_map(self::minutes(...), $scripts);

        $course = DB::transaction(function () use ($scripts, $minutes, $reviewers, $actor): Course {
            $course = Course::withTrashed()->firstOrNew(['slug' => self::SLUG]);
            $course->fill([
                'organisation_id' => null,
                'title' => 'Exam success: Texas',
                'type' => CourseType::TestPrep,
                'description' => 'Eight short lessons on how the Texas sales agent exam works and how to take it: timing, reading tricky questions, eliminating wrong answers, math shortcuts, Texas portion traps, your last 7 days and exam-day nerves.',
                'states' => ['TX'],
                'hours' => round(array_sum($minutes) / 60, 1),
            ]);
            if (! $course->exists) {
                $course->status = CourseStatus::Draft;
            }
            $course->deleted_at = null;
            $course->save();
            $course->forceFill(['builder' => ['language' => 'both', 'presenter' => 'stock'] + ($course->builder ?? [])])->save();
            $course->instructors()->syncWithoutDetaching(array_map(static fn (User $u): int => $u->id, $reviewers));
            $module = CourseModule::query()->firstOrCreate(['course_id' => $course->id, 'title' => self::MODULE], ['position' => 1]);

            foreach ($scripts as $n => $script) {
                $this->lesson($course, $module, $script, $n, $minutes[$n], $actor);
            }

            return $course;
        });

        foreach (Lesson::query()->where('course_id', $course->id)->with('versions')->get() as $lesson) {
            $version = $lesson->versions->sortByDesc('version')->first();

            if ($version !== null && $version->status === LessonVersion::RENDERING && $version->renders()->doesntExist()) {
                $this->pipeline->startRenders($version);
            }
        }

        $this->audit->record('course.exam_success_installed', 'course', (string) $course->id, ['lessons' => count($scripts), 'reviewers' => count($reviewers)], $actor);

        return $course;
    }

    /**
     * The script as the lesson's text, by heading (read on screen and searched once published).
     *
     * @param  Script  $script
     */
    public static function body(array $script, string $language = 'en'): string
    {
        $parts = [];
        foreach ($script['sections'] as $section) {
            $parts[] = '# '.$section['heading'][$language]."\n".implode(' ', array_column($section['lines'], $language));
        }

        return implode("\n\n", $parts);
    }

    /** @param  Script  $script */
    private static function minutes(array $script): int
    {
        $words = str_word_count(self::body($script));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /** @param  Script  $script */
    private function lesson(Course $course, CourseModule $module, array $script, int $n, int $minutes, ?User $actor): void
    {
        $source = ($n + 1).'. '.$script['title']['en'];
        $text = self::body($script);
        $path = 'platform/courses/'.$course->id.'/sources/exam-success-'.$script['key'].'.md';
        LessonFiles::disk()->put($path, $text);
        $module->sources()->updateOrCreate(['original_name' => $source.'.md'], [
            'role' => SourceRole::Script, 'kind' => MediaKind::Document, 'path' => $path, 'mime' => 'text/markdown', 'size' => strlen($text),
            'status' => MediaStatus::Ready, 'scanned_at' => now(), 'transcript' => $text, 'position' => $n + 1, 'words' => str_word_count($text),
            'sections' => [['page' => null, 'text' => $text]], 'created_by' => $actor?->id,
        ]);

        if (Lesson::withTrashed()->where('course_id', $course->id)->where('title', $script['title']['en'])->exists()) {
            return;
        }

        $lesson = Lesson::query()->create([
            'course_id' => $course->id, 'course_module_id' => $module->id, 'title' => $script['title']['en'], 'position' => $n + 1,
            'language' => 'en', 'body' => $text, 'minutes' => $minutes, 'review_status' => LessonReview::InReview,
        ]);

        $approved = $course->missingApprovals() === [] && $course->approvalNumbers() !== [];
        $lines = [];
        $flags = [];
        foreach ($script['sections'] as $section) {
            foreach ($section['lines'] as $line) {
                $i = count($lines);
                $lines[] = ['n' => $i, 'en' => $line['en'], 'es' => $line['es'], 'source' => $source, 'page' => null];

                foreach (['en', 'es'] as $language) {
                    foreach (ContentChecks::rulesBrokenBy($line[$language], $approved) as $rule) {
                        $flags[] = ['line' => $i, 'rule' => $rule, 'language' => $language];
                    }
                }
            }
        }

        $settings = $this->builder->settings($course);
        $version = LessonVersion::query()->create([
            'lesson_id' => $lesson->id, 'version' => 1, 'sources' => [['source' => $source, 'page' => null, 'text' => $text]],
            'presenter' => $settings['presenter'], 'vendor' => $settings['vendor'], 'created_by' => $actor?->id, 'status' => LessonVersion::DRAFTING,
        ]);
        $version->forceFill([
            'lines' => $lines, 'flags' => $flags, 'credits' => 0, 'review_state' => 'in_review',
            'status' => $flags === [] ? LessonVersion::RENDERING : LessonVersion::FLAGGED,
        ])->save();
        $lesson->forceFill(['review_status' => $flags === [] ? LessonReview::Generating : LessonReview::InReview])->save();

        foreach ($script['questions'] as $question) {
            ReviewItem::query()->create([
                'lesson_id' => $lesson->id, 'lesson_version_id' => $version->id, 'kind' => 'question', 'after_line' => null,
                'topic' => $question['topic'], 'difficulty' => 'medium', 'portion' => $question['topic'] === 'state_rules' ? 'state' : 'national',
                'content' => ['en' => $question['en'], 'es' => $question['es'], 'correct' => $question['correct'], 'source' => $source, 'page' => null],
                'status' => ReviewItem::TO_REVIEW,
            ]);
        }
    }
}
