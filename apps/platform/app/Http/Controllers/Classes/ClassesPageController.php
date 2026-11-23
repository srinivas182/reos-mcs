<?php

declare(strict_types=1);

namespace App\Http\Controllers\Classes;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VideoSession;
use App\Video\Classes\ClassReports;
use App\Video\Classes\LiveClasses;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live classes on the web (A10.2b; Web App Journey w505 for instructors,
 * the course's Live classes for candidates): the week's schedule with
 * registrations and CE label, Schedule a class, and Register.
 */
final class ClassesPageController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, LiveClasses $classes, VideoSessions $sessions): Response
    {
        $host = $sessions->canHost($user);
        $course = $request->integer('course');

        return Inertia::render('classes/index', [
            'classes' => $host ? $classes->forHost($user) : $classes->forStudent($user, $course > 0 ? $course : null),
            'can_schedule' => $host,
            'courses' => $host ? $classes->courses($user) : [],
            'lengths' => LiveClasses::LENGTHS,
        ]);
    }

    /** The CE class report (i13; w505): for the instructor, and the school's admins. */
    public function report(VideoSession $session, #[CurrentUser] User $user, ClassReports $reports): Response
    {
        return Inertia::render('classes/report', ['report' => $reports->report($session, $user)]);
    }
}
