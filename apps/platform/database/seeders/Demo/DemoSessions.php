<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Models\VideoSession;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Video sessions (B6.3): Dr. Rachel Hughes with her candidate Sofia Reyes (a
 * 1-on-1 contract review tomorrow and a live exam-prep class), a past
 * session with shared notes, and Dana Brooks mentoring James Miller.
 */
final readonly class DemoSessions
{
    public function __construct(private VideoSessions $sessions) {}

    public function seed(): void
    {
        $rachel = User::query()->where('email', 'rachel.hughes@demo.reos.test')->first();
        $sofia = User::query()->where('email', 'sofia.reyes@demo.reos.test')->first();
        $dana = User::query()->where('email', 'dana.brooks@demo.reos.test')->first();
        $james = User::query()->where('email', 'james.miller@demo.reos.test')->first();

        if ($rachel === null || $sofia === null || $dana === null || $james === null || VideoSession::query()->exists()) {
            return;
        }

        $at = fn (int $days, string $time): CarbonImmutable => CarbonImmutable::parse($time, 'America/Chicago')->addDays($days)->utc();

        $this->sessions->schedule($rachel, ['kind' => VideoSession::ONE_ON_ONE, 'title' => 'Contract review: TREC One to Four', 'agenda' => "Paragraphs 5 and 6 (earnest money and option fee)\nYour questions from the practice exam", 'starts_at' => $at(1, '15:00'), 'minutes' => 45, 'guests' => [$sofia->id]]);
        $this->sessions->schedule($rachel, ['kind' => VideoSession::LIVE_CLASS, 'title' => 'Exam prep: contracts and disclosures', 'agenda' => null, 'starts_at' => $at(2, '18:00'), 'minutes' => 60, 'guests' => [$sofia->id]]);
        $this->sessions->schedule($dana, ['kind' => VideoSession::ONE_ON_ONE, 'title' => 'Listing presentation practice', 'agenda' => 'Run through the Elm St listing presentation before Thursday.', 'starts_at' => $at(2, '10:00'), 'minutes' => 30, 'guests' => [$james->id]]);

        // Last week's session, with its notes.
        $past = VideoSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'organisation_id' => $this->sessions->hostOrganisation($rachel)?->id,
            'host_id' => $rachel->id,
            'kind' => VideoSession::ONE_ON_ONE,
            'title' => 'Getting started with the practice exams',
            'starts_at' => $at(-6, '16:00'),
            'ends_at' => $at(-6, '16:30'),
            'status' => VideoSession::ENDED,
            'started_at' => $at(-6, '15:58'),
            'ended_at' => $at(-6, '16:30'),
            'notes' => "Practice exam score: 68%. Focus on agency and contracts.\nNext: one full exam a week, review the missed questions with the AI tutor.",
            'notes_updated_by' => $rachel->id,
            'notes_updated_at' => $at(-6, '16:31'),
        ]);
        $past->participants()->create(['user_id' => $rachel->id, 'role' => 'host', 'first_joined_at' => $at(-6, '15:58'), 'last_joined_at' => $at(-6, '15:58'), 'joins' => 1]);
        $past->participants()->create(['user_id' => $sofia->id, 'role' => 'guest', 'first_joined_at' => $at(-6, '16:00'), 'last_joined_at' => $at(-6, '16:00'), 'joins' => 1]);
    }
}
