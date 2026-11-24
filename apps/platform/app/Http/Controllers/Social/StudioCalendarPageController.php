<?php

declare(strict_types=1);

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Social\SocialCalendar;
use App\Studio\Celebrations\Celebrations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The content calendar on the web (B12.3, ADR 0052; part of agent web
 * screen 17): a week or month of posts, drag (or Move) to another day,
 * reuse a post, plan a post on a day; and today's celebration.
 */
final class StudioCalendarPageController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, SocialCalendar $calendar, Celebrations $celebrations): Response
    {
        $view = $request->query('view') === 'month' ? 'month' : 'week';
        $date = $request->query('date');

        return Inertia::render('studio/calendar', [
            'calendar' => $calendar->view($user, $view, is_string($date) ? $date : null),
            'celebrations' => $celebrations->list($user),
        ]);
    }
}
