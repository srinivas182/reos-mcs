<?php

declare(strict_types=1);

namespace App\Content\Ai;

use App\Content\Avatars\AvatarConsents;
use App\Content\Avatars\AvatarVideo;
use App\Content\Avatars\AvatarVideos;
use App\Content\Avatars\SimulatedAvatarVideo;
use App\Content\Builder\CourseBuilder;
use App\Content\Builder\LessonReview;
use App\Content\Builder\ModuleSources;
use App\Content\Extraction\Captions;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Content\Review\ContentChecks;
use App\Jobs\DraftLessonVersion;
use App\Jobs\ProcessLessonMedia;
use App\Models\AvatarConsent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonRender;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Audit\AuditLogger;
use App\Services\Content\LessonFiles;
use App\Services\Content\Lessons;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The AI lesson pipeline (A7.3, ADR 0026; screen 43's "Generate module"):
 *
 * 1. `generate`: the module's sources are split into lessons of the
 *    course's length; each lesson gets a new version, queued for drafting.
 *    The school's credits are checked first (charged to the school).
 * 2. `draft` (queued): Claude writes the script from those sources only,
 *    every line citing its passage, and translates it line by line. The
 *    legal-line checks run on every line in both languages; a flagged line
 *    stops the version before anything renders. Check-ins, questions and
 *    flashcards go into review. The school is charged.
 * 3. Rendering: the presenter (a consented instructor's twin or the stock
 *    avatar) and the script go to the course's vendor once per language.
 * 4. `checkRenders` (every two minutes): a finished video is copied into
 *    REOS's storage and video pipeline with captions in both languages,
 *    timed to it, and the vendor's copy is deleted. When every language is
 *    in, the lesson waits for the instructor's review (A8.1).
 *
 * @phpstan-import-type Passage from LessonWriter
 * @phpstan-import-type Draft from LessonWriter
 */
final readonly class LessonPipeline
{
    public const FEATURE = 'ai_lesson';

    /** Check every render this often (seconds). */
    public const CHECK_EVERY = 110;

    public function __construct(
        private CourseBuilder $builder,
        private ModuleSources $sources,
        private Lessons $lessons,
        private LessonWriters $writers,
        private AvatarVideos $videos,
        private AvatarConsents $avatars,
        private AiCredits $credits,
        private AiSettings $ai,
        private AuditLogger $audit,
        private Tenancy $tenancy,
    ) {}

    /**
     * The languages a course's lessons are spoken in (decision 4: both, unless the course says one).
     *
     * @return list<'en'|'es'>
     */
    public function languages(Course $course): array
    {
        return match ($this->builder->settings($course)['language']) {
            'en' => ['en'],
            'es' => ['es'],
            default => ['en', 'es'],
        };
    }

    /**
     * Drafts every lesson of a module from its sources.
     *
     * @return list<Lesson>
     */
    public function generate(Course $course, CourseModule $module, int $checkinMinutes, User $actor): array
    {
        $school = $course->owner ?? throw ValidationException::withMessages(['module' => __('builder.errors.noSchool')]);
        $settings = $this->builder->settings($course);
        $this->presenter($course, $school, $settings['presenter'], $settings['vendor']);

        if ($module->lessons()->where('review_status', LessonReview::Generating->value)->exists()) {
            throw ValidationException::withMessages(['module' => __('builder.errors.generating')]);
        }

        $passages = $this->sources->passages($module);

        if ($passages === []) {
            throw ValidationException::withMessages(['module' => __('builder.errors.noSources')]);
        }

        $plan = $this->builder->plan($course, $module, $checkinMinutes);

        if ($this->credits->schoolLeft($school) < $plan['credits']) {
            throw ValidationException::withMessages(['module' => __('builder.errors.credits', ['credits' => $plan['credits'], 'left' => $this->credits->schoolLeft($school)])]);
        }

        $groups = self::split($passages, max(1, $settings['lesson_minutes']) * CourseBuilder::WORDS_PER_MINUTE);
        $created = DB::transaction(function () use ($course, $module, $groups, $settings, $actor): array {
            $created = [];

            foreach ($groups as $n => $group) {
                $lesson = $this->lessons->create($course, ['title' => $module->title.' · '.__('builder.ai.part', ['n' => $n + 1]), 'course_module_id' => $module->id, 'language' => 'en'], $actor);
                $lesson->forceFill(['review_status' => LessonReview::Generating, 'minutes' => self::minutes($group)])->save();
                $this->newVersion($lesson, $group, $settings['presenter'], $settings['vendor'], $actor);
                $created[] = $lesson;
            }

            return $created;
        });

        $this->audit->record('module.generated', 'course_module', (string) $module->id, ['lessons' => count($created), 'credits' => $plan['credits'], 'vendor' => $settings['vendor']], $actor, $school->id);

        foreach ($created as $lesson) {
            $version = $lesson->versions()->reorder('version', 'desc')->firstOrFail();
            DraftLessonVersion::dispatch($version->id, $checkinMinutes);
        }

        return $created;
    }

    /** Generates a lesson again (a new version; earlier ones are kept). */
    public function regenerate(Lesson $lesson, int $checkinMinutes, User $actor): LessonVersion
    {
        $course = $lesson->course;
        $school = $course->owner ?? throw ValidationException::withMessages(['lesson' => __('builder.errors.noSchool')]);
        $settings = $this->builder->settings($course);
        $this->presenter($course, $school, $settings['presenter'], $settings['vendor']);
        $last = $lesson->versions()->reorder('version', 'desc')->first() ?? throw ValidationException::withMessages(['lesson' => __('builder.errors.notAi')]);

        if (in_array($last->status, [LessonVersion::DRAFTING, LessonVersion::RENDERING], true)) {
            throw ValidationException::withMessages(['lesson' => __('builder.errors.generating')]);
        }

        if ($this->credits->schoolLeft($school) < $this->cost($course, $last->sources)) {
            throw ValidationException::withMessages(['lesson' => __('builder.errors.credits', ['credits' => $this->cost($course, $last->sources), 'left' => $this->credits->schoolLeft($school)])]);
        }

        $version = $this->newVersion($lesson, $last->sources, $settings['presenter'], $settings['vendor'], $actor);
        $lesson->forceFill(['review_status' => LessonReview::Generating])->save();
        $this->audit->record('lesson.regenerated', Lessons::AUDIT_SUBJECT, (string) $lesson->id, ['version' => $version->version], $actor, $school->id);
        DraftLessonVersion::dispatch($version->id, $checkinMinutes);

        return $version;
    }

    /**
     * What a lesson from these passages costs: minutes × voices × the per-minute price (screen 69).
     *
     * @param  list<Passage>  $passages
     */
    public function cost(Course $course, array $passages): int
    {
        return self::minutes($passages) * count($this->languages($course)) * $this->ai->cost('ai_lesson_minute');
    }

    /** Step 2 (queued): the script, its translation, the legal checks and the review items. */
    public function draft(LessonVersion $version, int $checkinMinutes): void
    {
        if ($version->status !== LessonVersion::DRAFTING) {
            return;
        }

        $lesson = $version->lesson;
        $course = $lesson->course;
        $school = $course->owner;
        $writer = $this->writers->writer();

        try {
            $draft = $writer->write([
                'title' => $lesson->title, 'minutes' => max(1, self::minutes($version->sources)), 'checkin_minutes' => max(1, $checkinMinutes),
                'state' => 'Texas', 'passages' => $version->sources,
            ]);

            if ($draft['lines'] === []) {
                throw new RuntimeException('The draft has no lines.');
            }

            $spanish = $writer->translate(self::texts($draft));
        } catch (Throwable $e) {
            report($e);
            $this->fail($version, 'builder.ai.failed.draft');

            return;
        }

        $approved = $course->missingApprovals() === [] && $course->approvalNumbers() !== [];
        $at = 0;
        // The Spanish comes back in the order of `texts()`; each call takes the next one.
        $es = static function () use (&$at, $spanish): string {
            return $spanish[$at++] ?? '';
        };
        $title = ['en' => $draft['title'], 'es' => $es()];
        $lines = [];
        $flags = [];

        foreach ($draft['lines'] as $n => $line) {
            $passage = $version->sources[$line['source']];
            $lines[] = ['n' => $n, 'en' => $line['text'], 'es' => $es(), 'source' => $passage['source'], 'page' => $passage['page']];

            foreach (['en', 'es'] as $language) {
                foreach (ContentChecks::rulesBrokenBy($lines[$n][$language], $approved) as $rule) {
                    $flags[] = ['line' => $n, 'rule' => $rule, 'language' => $language];
                }
            }
        }

        $items = [];
        foreach ($draft['checkins'] as $checkin) {
            $items[] = ['kind' => 'checkin', 'after_line' => $checkin['after_line'], 'topic' => null, 'content' => ['en' => ['prompt' => $checkin['prompt']], 'es' => ['prompt' => $es()]]];
        }

        foreach ($draft['questions'] as $question) {
            $passage = $version->sources[$question['source']];
            $en = ['prompt' => $question['prompt'], 'options' => $question['options'], 'explanation' => $question['explanation']];
            $prompt = $es();
            $options = [];
            foreach ($question['options'] as $ignored) {
                $options[] = $es();
            }
            $translated = ['prompt' => $prompt, 'options' => $options, 'explanation' => $es()];
            $items[] = ['kind' => 'question', 'after_line' => null, 'topic' => $question['topic'], 'difficulty' => 'medium', 'portion' => $question['topic'] === 'state_rules' ? 'state' : 'national', 'content' => ['en' => $en, 'es' => $translated, 'correct' => $question['correct'], 'source' => $passage['source'], 'page' => $passage['page']]];
        }

        foreach ($draft['flashcards'] as $card) {
            $passage = $version->sources[$card['source']];
            $items[] = ['kind' => 'flashcard', 'after_line' => null, 'topic' => null, 'content' => ['en' => ['front' => $card['front'], 'back' => $card['back']], 'es' => ['front' => $es(), 'back' => $es()], 'source' => $passage['source'], 'page' => $passage['page']]];
        }

        $credits = $this->cost($course, $version->sources);
        $flagged = $flags !== [];

        DB::transaction(function () use ($version, $lesson, $lines, $flags, $items, $title, $credits, $flagged): void {
            $version->forceFill(['lines' => $lines, 'flags' => $flags, 'credits' => $credits, 'review_state' => 'in_review', 'status' => $flagged ? LessonVersion::FLAGGED : LessonVersion::RENDERING])->save();
            $lesson->forceFill(['title' => Str::limit($title['en'], 200, ''), 'review_status' => $flagged ? LessonReview::InReview : LessonReview::Generating])->save();

            foreach ($items as $item) {
                ReviewItem::query()->create([...$item, 'lesson_id' => $lesson->id, 'lesson_version_id' => $version->id, 'status' => ReviewItem::TO_REVIEW]);
            }
        });

        if ($school !== null && $version->created_by !== null && ($actor = User::query()->find($version->created_by)) !== null) {
            $this->credits->chargeSchool($school, $actor, self::FEATURE, $writer->engine(), $credits, (string) $version->id);
        }

        if (! $flagged) {
            $this->startRenders($version);
        }
    }

    /**
     * Renders an edited script again (A8.1, "Re-record line"): a new version
     * with the reviewed lines and their review carried over, straight to the
     * vendor (no new draft). The earlier version is kept.
     */
    public function rerender(LessonVersion $from, User $actor): LessonVersion
    {
        $lesson = $from->lesson;
        $course = $lesson->course;
        $school = $course->owner ?? throw ValidationException::withMessages(['lesson' => __('builder.errors.noSchool')]);
        $credits = $this->cost($course, $from->sources);

        if ($this->credits->schoolLeft($school) < $credits) {
            throw ValidationException::withMessages(['lesson' => __('builder.errors.credits', ['credits' => $credits, 'left' => $this->credits->schoolLeft($school)])]);
        }

        $settings = $this->builder->settings($course);
        $version = $this->newVersion($lesson, $from->sources, $settings['presenter'], $settings['vendor'], $actor);
        $reviews = [];
        foreach ($from->reviews ?? [] as $n => $review) {
            $reviews[$n] = [...$review, 'rerecord' => false];
        }
        $version->forceFill(['lines' => $from->lines, 'flags' => $from->flags, 'reviews' => $reviews, 'review_state' => 'in_review', 'credits' => $credits, 'status' => LessonVersion::RENDERING])->save();
        DB::table('review_items')->where('lesson_version_id', $from->id)->update(['lesson_version_id' => $version->id]);
        $lesson->forceFill(['review_status' => LessonReview::Generating])->save();
        $this->credits->chargeSchool($school, $actor, self::FEATURE, 'render', $credits, (string) $version->id);
        $this->audit->record('lesson.rerendered', Lessons::AUDIT_SUBJECT, (string) $lesson->id, ['version' => $version->version], $actor, $school->id);
        $this->startRenders($version);

        return $version;
    }

    /** Step 3: one video per language, at the course's vendor. */
    public function startRenders(LessonVersion $version): void
    {
        $course = $version->lesson->course;
        $driver = $this->videos->for($version->vendor);
        $school = $course->owner;
        $avatar = $school === null ? '' : $this->avatarId($version->presenter, $school, $driver);

        foreach ($this->languages($course) as $language) {
            $render = LessonRender::query()->firstOrCreate(
                ['lesson_version_id' => $version->id, 'language' => $language],
                ['vendor' => $driver->vendor(), 'status' => LessonRender::QUEUED, 'simulated' => $driver->simulated()],
            );

            if ($render->status !== LessonRender::QUEUED) {
                continue;
            }

            try {
                $script = implode("\n", array_map(static fn (array $line): string => self::said($line, $language), $version->lines ?? []));
                $id = $driver->start($avatar, $language, $script, $version->lesson->title.' ('.$language.')', 'reos-render-'.$render->id);
                $render->forceFill(['external_id' => $id, 'status' => LessonRender::RENDERING, 'checked_at' => null])->save();
            } catch (Throwable $e) {
                report($e);
                $render->forceFill(['status' => LessonRender::FAILED, 'failure' => 'builder.ai.failed.render'])->save();
            }
        }

        $this->settle($version->refresh());
    }

    /** Step 4 (every two minutes): checks each video still rendering. Returns how many finished. */
    public function checkRenders(): int
    {
        $done = 0;
        $due = LessonRender::query()->where('status', LessonRender::RENDERING)
            ->where(static fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subSeconds(self::CHECK_EVERY)))
            ->orderBy('id')->limit(50)->get();

        foreach ($due as $render) {
            $done += $this->check($render) ? 1 : 0;
        }

        return $done;
    }

    public function check(LessonRender $render): bool
    {
        $external = $render->external_id;

        if ($render->status !== LessonRender::RENDERING || $external === null) {
            return false;
        }

        $driver = $render->simulated ? new SimulatedAvatarVideo : $this->videos->for($render->vendor);

        try {
            $result = $driver->check($external);
        } catch (Throwable $e) {
            report($e);
            $result = ['status' => 'rendering', 'url' => null, 'seconds' => null, 'error' => null];
        }

        $render->forceFill(['checks' => $render->checks + 1, 'checked_at' => now()])->save();

        if ($result['status'] === 'rendering') {
            if ($render->checks >= LessonRender::MAX_CHECKS) {
                $render->forceFill(['status' => LessonRender::FAILED, 'failure' => 'builder.ai.failed.timeout'])->save();
                $this->settle($render->version);
            }

            return false;
        }

        if ($result['status'] === 'failed') {
            $render->forceFill(['status' => LessonRender::FAILED, 'failure' => 'builder.ai.failed.render'])->save();
            $this->settle($render->version);

            return false;
        }

        try {
            $this->keep($render, $result['url'], $result['seconds']);
        } catch (Throwable $e) {
            report($e);
            $render->forceFill(['status' => LessonRender::FAILED, 'failure' => 'builder.ai.failed.copy'])->save();
            $this->settle($render->version);

            return false;
        }

        try {
            $driver->delete($external);
        } catch (Throwable $e) {
            report($e);
        }

        $this->settle($render->version);

        return true;
    }

    /** Copies the finished video into REOS's storage, with captions in both languages, and sends it to the video pipeline. */
    private function keep(LessonRender $render, ?string $url, ?int $seconds): void
    {
        $version = $render->version;
        $lesson = $version->lesson;
        $owner = $lesson->course->organisation_id;
        $path = ($owner === null ? 'platform' : 'tenants/'.$owner).'/lessons/'.$lesson->id.'/ai-v'.$version->version.'-'.$render->language.'-'.Str::uuid7()->toString().'.mp4';
        $disk = LessonFiles::disk();

        if ($render->simulated || $url === null) {
            $stream = fopen(SimulatedAvatarVideo::placeholder(), 'rb');
        } else {
            $local = tempnam(sys_get_temp_dir(), 'reos-render-');
            if ($local === false) {
                throw new RuntimeException('No temporary file.');
            }
            Http::timeout(600)->sink($local)->get($url)->throw();
            $stream = fopen($local, 'rb');
            @unlink($local);
        }

        if (! is_resource($stream)) {
            throw new RuntimeException('The video could not be read.');
        }

        try {
            $disk->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        $lines = $version->lines ?? [];
        $length = $seconds ?? max(1, (int) ceil(60 * array_sum(array_map(static fn (array $l): int => str_word_count(self::said($l, $render->language)), $lines)) / CourseBuilder::WORDS_PER_MINUTE));
        $captions = [];
        foreach (['en', 'es'] as $language) {
            $captions[$language] = Captions::write(array_map(static fn (array $l): string => self::said($l, $language), $lines), $length);
        }

        $media = DB::transaction(function () use ($lesson, $render, $path, $disk, $captions, $length, $version): LessonMedia {
            $media = LessonMedia::query()->create([
                'lesson_id' => $lesson->id, 'kind' => MediaKind::Video, 'language' => $render->language,
                'path' => $path, 'original_name' => Str::limit($lesson->title, 150, '').' (v'.$version->version.', '.$render->language.').mp4',
                'mime' => 'video/mp4', 'size' => (int) $disk->size($path), 'status' => MediaStatus::Processing,
                'transcript' => $captions[$render->language], 'captions' => $captions, 'duration_seconds' => $length,
            ]);
            $render->forceFill(['status' => LessonRender::READY, 'lesson_media_id' => $media->id, 'seconds' => $length])->save();

            return $media;
        });

        ProcessLessonMedia::dispatch($media->id);
    }

    /** A version is ready once every language is in, failed if any language failed. */
    private function settle(LessonVersion $version): void
    {
        $renders = $version->renders()->get();

        if ($renders->isEmpty() || $version->status !== LessonVersion::RENDERING) {
            return;
        }

        if ($renders->contains(static fn (LessonRender $r): bool => $r->status === LessonRender::FAILED)) {
            $this->fail($version, 'builder.ai.failed.render');

            return;
        }

        if ($renders->every(static fn (LessonRender $r): bool => $r->status === LessonRender::READY)) {
            $version->forceFill(['status' => LessonVersion::READY])->save();
            $version->lesson->forceFill(['review_status' => LessonReview::InReview])->save();
            // Older versions' videos make way for this one's (the versions themselves are kept).
            LessonMedia::query()->where('lesson_id', $version->lesson_id)->where('kind', MediaKind::Video->value)->whereNotNull('language')
                ->whereNotIn('id', $renders->pluck('lesson_media_id')->filter())->get()
                ->each(static function (LessonMedia $old): void {
                    LessonFiles::disk()->delete($old->path);
                    $old->delete();
                });
        }
    }

    private function fail(LessonVersion $version, string $reason): void
    {
        $version->forceFill(['status' => LessonVersion::FAILED, 'failure' => $reason])->save();
        $lesson = $version->lesson;
        // A lesson whose first version failed goes back to draft; one with an earlier version waits in review.
        $earlier = $lesson->versions()->where('id', '!=', $version->id)->where('status', LessonVersion::READY)->exists();
        $lesson->forceFill(['review_status' => $earlier ? LessonReview::InReview : LessonReview::Draft])->save();
    }

    /**
     * The presenter must be usable now: the stock avatar, or an instructor
     * of the course whose consent is in force and whose twin is set up at
     * the vendor.
     */
    private function presenter(Course $course, Organisation $school, string $presenter, string $vendor): void
    {
        if ($presenter === 'stock') {
            return;
        }

        $consent = $this->consentFor($presenter, $school);
        $listed = $this->tenancy->run($school->id, fn (): array => array_column($this->builder->presenters($course), 'value'));

        if ($consent === null || ! in_array($presenter, $listed, true)) {
            throw ValidationException::withMessages(['presenter' => __('builder.errors.presenter')]);
        }

        if ($this->videos->for($vendor)->simulated() === false && ($consent->vendor_avatars[$vendor] ?? '') === '') {
            throw ValidationException::withMessages(['presenter' => __('builder.errors.noTwin')]);
        }
    }

    private function avatarId(string $presenter, Organisation $school, AvatarVideo $driver): string
    {
        if ($presenter === 'stock') {
            return $driver->stockAvatar();
        }

        $consent = $this->consentFor($presenter, $school);

        return $consent === null || $driver->simulated() ? $driver->stockAvatar() : ($consent->vendor_avatars[$driver->vendor()] ?? '');
    }

    private function consentFor(string $presenter, Organisation $school): ?AvatarConsent
    {
        $id = str_starts_with($presenter, 'instructor:') ? (int) substr($presenter, 11) : 0;
        $instructor = $id > 0 ? User::query()->find($id) : null;

        // Avatar consents are kept under row-level security: read them as the school.
        return $instructor === null ? null : $this->tenancy->run($school->id, fn (): ?AvatarConsent => $this->avatars->usable($instructor, $school) ? $this->avatars->for($instructor, $school) : null);
    }

    /** @param  list<Passage>  $passages */
    private function newVersion(Lesson $lesson, array $passages, string $presenter, string $vendor, User $actor): LessonVersion
    {
        $max = $lesson->versions()->max('version');
        $next = (is_numeric($max) ? (int) $max : 0) + 1;

        return LessonVersion::query()->create([
            'lesson_id' => $lesson->id, 'version' => $next, 'status' => LessonVersion::DRAFTING, 'sources' => $passages,
            'presenter' => $presenter, 'vendor' => $vendor, 'created_by' => $actor->id,
        ]);
    }

    /**
     * Every English text of a draft in a fixed order (title, lines, check-ins,
     * then each question's prompt, options and explanation, then each
     * flashcard's front and back), for translating in one go.
     *
     * @param  Draft  $draft
     * @return list<string>
     */
    private static function texts(array $draft): array
    {
        $texts = [$draft['title']];
        foreach ($draft['lines'] as $line) {
            $texts[] = $line['text'];
        }
        foreach ($draft['checkins'] as $checkin) {
            $texts[] = $checkin['prompt'];
        }
        foreach ($draft['questions'] as $question) {
            array_push($texts, $question['prompt'], ...$question['options']);
            $texts[] = $question['explanation'];
        }
        foreach ($draft['flashcards'] as $card) {
            array_push($texts, $card['front'], $card['back']);
        }

        return $texts;
    }

    /**
     * The module's passages in lessons of about so many words, never splitting a passage.
     *
     * @param  list<Passage>  $passages
     * @return list<list<Passage>>
     */
    public static function split(array $passages, int $words): array
    {
        $groups = [];
        $current = [];
        $count = 0;

        foreach ($passages as $passage) {
            $size = str_word_count($passage['text']);

            if ($current !== [] && $count + $size > $words) {
                $groups[] = $current;
                $current = [];
                $count = 0;
            }

            $current[] = $passage;
            $count += $size;
        }

        if ($current !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /** @param  array{en: string, es: string}  $line */
    private static function said(array $line, string $language): string
    {
        return $language === 'es' ? $line['es'] : $line['en'];
    }

    /** @param  list<Passage>  $passages */
    private static function minutes(array $passages): int
    {
        return max(1, (int) ceil(array_sum(array_map(static fn (array $p): int => str_word_count($p['text']), $passages)) / CourseBuilder::WORDS_PER_MINUTE));
    }
}
