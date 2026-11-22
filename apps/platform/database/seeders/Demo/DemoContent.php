<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Knowledge\Ingestion;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Models\Course;
use App\Models\Lesson;
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

    public function __construct(private Ingestion $ingestion) {}

    public function seed(): void
    {
        $principles = $this->course('principles-of-real-estate-ii', 'Principles of Real Estate II', CourseType::Qualifying, 30, CourseStatus::Live, ['TX' => ['number' => 'TREC 0451', 'expires_on' => '2028-08-31']]);
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

        $this->course('contracts-for-new-agents', 'Contracts for new agents', CourseType::Ce, 4, CourseStatus::Draft, null);

        // Waiting for review with one legal flag (screen 63).
        $strategies = $this->course('exam-day-strategies', 'Exam day strategies', CourseType::TestPrep, 2, CourseStatus::InReview, null);
        $this->lesson($strategies, 'Pacing the national portion', 'en', 12,
            "# Pacing\nAllow about one minute per question and flag anything that takes longer. "
            .'Candidates who follow this plan are guaranteed to pass on the first try.');

        $exam = $this->course('exito-en-el-examen-texas', 'Éxito en el examen: Texas', CourseType::TestPrep, null, CourseStatus::Live, null);
        $this->lesson($exam, 'Representación y divulgación', 'es', 15,
            "# Primer contacto\nEntregue el formulario de Información sobre Servicios de Corretaje (IABS) en el primer contacto sustancial. "
            .'Explique a quién representa antes de hablar de precio o condiciones.');
    }

    /** @param  array<string, array{number: string, expires_on: string|null}>|null  $approvals */
    private function course(string $slug, string $title, CourseType $type, ?int $hours, CourseStatus $status, ?array $approvals): Course
    {
        return Course::withTrashed()->updateOrCreate(['slug' => $slug], [
            'title' => $title,
            'type' => $type,
            'states' => ['TX'],
            'hours' => $hours,
            'approvals' => $approvals,
            'status' => $status,
            'published_at' => $status === CourseStatus::Live ? now() : null,
            'deleted_at' => null,
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
