<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learn;

use App\Content\Knowledge\SearchHit;
use App\Content\Learning\Learning;
use App\Content\Player\SeatTime;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Learn on the web (A5.3b; candidate "Courses", agent "Learn and CE"): the
 * live courses for the person's state, a search inside them, and a lesson
 * opened at the heading, page or moment a result points to (the first
 * shape of the course player, screen 5).
 */
final class LearnController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, Learning $learning): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:300'],
            'language' => ['nullable', Rule::in(Lesson::LANGUAGES)],
        ]);
        $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        $language = is_string($filters['language'] ?? null) ? $filters['language'] : null;

        return Inertia::render('learn/index', [
            'courses' => $learning->courses($user),
            'search' => [
                'q' => $q,
                'language' => $language,
                'results' => $q === '' ? null : array_map(static fn (SearchHit $hit): array => $hit->toArray(), $learning->search($user, $q, $language)),
            ],
        ]);
    }

    public function lesson(Request $request, int $lesson, #[CurrentUser] User $user, Learning $learning, SeatTime $seatTime): Response
    {
        $found = $learning->find($user, $lesson);
        abort_if($found === null, 404);

        $at = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            't' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'heading' => ['nullable', 'string', 'max:200'],
            // Ask why (A9.2e): a question for Ask the instructor, asked on opening.
            'ask' => ['nullable', 'string', 'max:400'],
        ]);

        if (! $learning->canOpen($user, $found)) {
            return Inertia::render('learn/locked', ['lesson' => ['id' => $found->id, 'title' => $found->title, 'course' => $found->course->title]]);
        }

        return Inertia::render('learn/lesson', [
            'lesson' => $learning->lesson($found, $user->locale->value),
            // Seat time and check-ins (A8.2).
            'player' => $seatTime->current($user, $found),
            'at' => [
                'page' => isset($at['page']) && is_numeric($at['page']) ? (int) $at['page'] : null,
                'seconds' => isset($at['t']) && is_numeric($at['t']) ? (int) $at['t'] : null,
                'heading' => is_string($at['heading'] ?? null) ? $at['heading'] : null,
                'ask' => is_string($at['ask'] ?? null) ? $at['ask'] : null,
            ],
        ]);
    }
}
