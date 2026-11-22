<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\Content\Courses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Legal flags on a course (A5.3b, screen 63): a reviewer clears a line
 * that's fine in context, and Undo puts the flag back. Every change audited.
 */
final class CourseFlagController extends Controller
{
    public function clear(Course $course, string $flag, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->clearFlag($course, $flag, $actor);

        return back();
    }

    public function restore(Course $course, string $flag, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->restoreFlag($course, $flag, $actor);

        return back();
    }
}
