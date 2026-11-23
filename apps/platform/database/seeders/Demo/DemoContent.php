<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Billing\Ledger\Ledger;
use App\Content\Ai\LessonPipeline;
use App\Content\Builder\LessonReview;
use App\Content\Builder\SourceRole;
use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Delivery;
use App\Content\Knowledge\Ingestion;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Content\LessonFiles;

/**
 * REOS content as on web journey screen 63 (A5.3): Principles of Real Estate
 * II (qualifying, TREC-approved, live) with the agency disclosure lesson and
 * its video captions; Contracts for new agents (CE, no approval number yet,
 * so hidden); and Exam success: Texas in Spanish. Their passages are built,
 * so the content library's search works at once; and Exam day strategies
 * waiting for review with a legal flag. Safe to run again.
 */
final readonly class DemoContent
{
    private const CAPTIONS = "WEBVTT\n\n00:00:01.000 --> 00:00:06.000\nWelcome back. Today we talk about agency disclosure in Texas.\n\n"
        ."00:00:20.000 --> 00:00:26.000\nEvery license holder must explain who they represent.\n\n"
        ."00:04:05.000 --> 00:04:12.000\nSo before you talk about price or terms, hand them the IABS form.\n\n"
        ."00:04:12.500 --> 00:04:18.000\nThat is the Information About Brokerage Services notice.\n";

    public function __construct(private Ingestion $ingestion, private LessonPipeline $pipeline, private Ledger $ledger) {}

    public function seed(): void
    {
        // Texas Realty School's own courses and their approvals (A7.1, screen 50).
        $school = Organisation::query()->where('name', 'Texas Realty School')->value('id');
        $principles = $this->course('principles-of-real-estate-ii', 'Principles of Real Estate II', CourseType::Qualifying, 30, CourseStatus::Live, is_int($school) ? $school : null);
        $this->approval($principles, Delivery::SelfPaced, 'TREC 0451', CourseApproval::APPROVED, '2024-09-01', '2028-08-31');
        // Filed with TREC for AI avatar delivery, waiting.
        $this->approval($principles, Delivery::AiAvatar, null, CourseApproval::PENDING, null, null);
        $agency = $this->lesson($principles, '6.2 Agency disclosure', 'en', 25,
            "# First contact\nGive the Information About Brokerage Services (IABS) form at the first substantive contact with a party. "
            ."A substantive contact is any talk about a specific property, price or terms.\n\n"
            ."# Who you represent\nExplain whether you represent the buyer, the seller, or act as an intermediary. "
            .'An intermediary needs written consent from both parties and must treat them fairly.');
        $this->video($agency);
        $this->captions($agency);
        $this->lesson($principles, '6.3 Intermediary and appointed agents', 'en', 20,
            "# Appointments\nWith written consent, the broker may appoint a different license holder to each party. "
            .'Appointed license holders may give opinions and advice to the party they were appointed to.');

        $this->builderModule($principles);
        $contracts = $this->course('contracts-for-new-agents', 'Contracts for new agents', CourseType::Ce, 4, CourseStatus::Draft, is_int($school) ? $school : null);
        // Expires in 25 days: on the school dashboard's "Needs attention".
        $this->approval($contracts, Delivery::SelfPaced, 'TREC CE 30117', CourseApproval::APPROVED, '2024-12-15', now()->addDays(25)->toDateString());

        // Dr. Rachel Hughes teaches both (A7.1b, screen 52); her avatar consent is hers to give.
        $rachel = User::query()->where('email', 'rachel.hughes@demo.reos.test')->value('id');

        if (is_int($rachel)) {
            $principles->instructors()->syncWithoutDetaching([$rachel]);
            $contracts->instructors()->syncWithoutDetaching([$rachel]);
        }

        // Waiting for review with one legal flag (screen 63).
        $strategies = $this->course('exam-day-strategies', 'Exam day strategies', CourseType::TestPrep, 2, CourseStatus::InReview);
        $this->lesson($strategies, 'Pacing the national portion', 'en', 12,
            "# Pacing\nAllow about one minute per question and flag anything that takes longer. "
            .'Candidates who follow this plan are guaranteed to pass on the first try.');

        $exam = $this->course('exito-en-el-examen-texas', 'Éxito en el examen: Texas', CourseType::TestPrep, null, CourseStatus::Live);
        $this->lesson($exam, 'Representación y divulgación', 'es', 15,
            "# Primer contacto\nEntregue el formulario de Información sobre Servicios de Corretaje (IABS) en el primer contacto sustancial. "
            .'Explique a quién representa antes de hablar de precio o condiciones.');
    }

    private function course(string $slug, string $title, CourseType $type, ?int $hours, CourseStatus $status, ?int $owner = null): Course
    {
        return Course::withTrashed()->updateOrCreate(['slug' => $slug], [
            'organisation_id' => $owner,
            'title' => $title,
            'type' => $type,
            'states' => ['TX'],
            'hours' => $hours,
            'status' => $status,
            'published_at' => $status === CourseStatus::Live ? now() : null,
            'deleted_at' => null,
        ]);
    }

    /**
     * The course builder (A7.2, screen 43): the agency lessons sit in a module
     * with its source material (a recorded class's captions, already read)
     * and one hand-written lesson still in draft.
     */
    private function builderModule(Course $course): void
    {
        $module = CourseModule::query()->firstOrCreate(['course_id' => $course->id, 'title' => 'Agency in Texas'], ['position' => 1]);
        Lesson::query()->where('course_id', $course->id)->whereNull('course_module_id')->update(['course_module_id' => $module->id]);

        $path = 'tenants/'.($course->organisation_id ?? 0).'/courses/'.$course->id.'/sources/agency-class-2027-02-10.vtt';
        LessonFiles::disk()->put($path, self::CAPTIONS);
        $module->sources()->updateOrCreate(['original_name' => 'Agency class, Feb 10 (captions).vtt'], [
            'role' => SourceRole::Captions, 'kind' => MediaKind::Captions, 'path' => $path, 'mime' => 'text/vtt', 'size' => strlen(self::CAPTIONS),
            'status' => MediaStatus::Ready, 'scanned_at' => now(), 'transcript' => self::CAPTIONS, 'position' => 1, 'words' => 52,
            'sections' => [['page' => null, 'text' => 'Welcome back. Today we talk about agency disclosure in Texas. Every license holder must explain who they represent. So before you talk about price or terms, hand them the IABS form. That is the Information About Brokerage Services notice.']],
        ]);

        $draft = Lesson::query()->firstOrCreate(['course_id' => $course->id, 'title' => '6.4 The IABS form, step by step'], [
            'course_module_id' => $module->id, 'language' => 'en', 'position' => 4, 'review_status' => LessonReview::Draft,
        ]);
        $draft->forceFill(['course_module_id' => $module->id, 'review_status' => LessonReview::Draft])->save();

        $this->aiLesson($course, $module);
    }

    /**
     * The school's AI credits and one AI lesson made end to end from the
     * module's captions (A7.3), with the simulators: the script with its
     * sources in English and Spanish, two placeholder videos with captions,
     * and its check-ins, questions and flashcards waiting for review.
     */
    private function aiLesson(Course $course, CourseModule $module): void
    {
        $school = $course->owner;
        $author = $course->instructors()->first() ?? User::query()->where('email', 'like', '%@%')->orderBy('id')->first();

        if ($school === null || $author === null) {
            return;
        }

        if (! $this->ledger->posted('pool_funded', 'demo', 'school-credits-'.$school->id)) {
            $this->ledger->post('pool_funded', [[$this->ledger->platform('credits_sold'), -5000], [$this->ledger->organisation($school, 'credits'), 5000]], 'demo', 'school-credits-'.$school->id, 'Demo credits');
        }

        if (LessonVersion::query()->whereIn('lesson_id', $module->lessons()->pluck('id'))->exists()) {
            return;
        }

        $queue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            $this->pipeline->generate($course, $module, 15, $author);
            $this->pipeline->checkRenders();
        } finally {
            config(['queue.default' => $queue]);
        }
    }

    private function approval(Course $course, Delivery $delivery, ?string $number, string $status, ?string $approvedOn, ?string $expiresOn): void
    {
        $course->approvals()->updateOrCreate(['state_code' => 'TX', 'delivery' => $delivery->value], [
            'number' => $number, 'hours' => $course->hours, 'status' => $status, 'approved_on' => $approvedOn, 'expires_on' => $expiresOn,
            'max_hours_per_day' => 12, 'checkin_minutes' => 15, 'presence_percent' => 100, 'makeup_min_percent' => 67, 'makeup_days' => 90,
        ]);
    }

    private function lesson(Course $course, string $title, string $language, int $minutes, string $body): Lesson
    {
        $lesson = Lesson::withTrashed()->updateOrCreate(['course_id' => $course->id, 'title' => $title], [
            'language' => $language,
            'minutes' => $minutes,
            'body' => $body,
            'position' => (int) Lesson::query()->where('course_id', $course->id)->count() + 1,
            'deleted_at' => null,
        ]);
        $this->ingestion->ingest($lesson);

        return $lesson;
    }

    /** The lesson video's captions, so what's said is searchable with its moment. */
    /**
     * A short demo video for the lesson (a title card with the clock and the
     * caption lines at their times), so the player shows and a transcript
     * line plays from its moment. Real lessons upload theirs in the content
     * library and stream through Mux.
     */
    private function video(Lesson $lesson): void
    {
        $path = 'platform/lessons/'.$lesson->id.'/demo-agency-disclosure.mp4';
        $bytes = (string) file_get_contents(__DIR__.'/assets/agency-disclosure.mp4');
        LessonFiles::disk()->put($path, $bytes);

        $lesson->media()->updateOrCreate(['path' => $path], [
            'kind' => MediaKind::Video,
            'original_name' => 'agency-disclosure.mp4',
            'mime' => 'video/mp4',
            'size' => strlen($bytes),
            'status' => MediaStatus::Ready,
            'duration_seconds' => 262,
            'scanned_at' => now(),
        ]);
    }

    private function captions(Lesson $lesson): void
    {
        $path = 'platform/lessons/'.$lesson->id.'/demo-captions.vtt';
        LessonFiles::disk()->put($path, self::CAPTIONS);

        $lesson->media()->updateOrCreate(['path' => $path], [
            'kind' => MediaKind::Captions,
            'original_name' => 'agency-disclosure.vtt',
            'mime' => 'text/vtt',
            'size' => strlen(self::CAPTIONS),
            'status' => MediaStatus::Ready,
            'transcript' => self::CAPTIONS,
            'scanned_at' => now(),
        ]);
        $this->ingestion->ingest($lesson);
    }
}
