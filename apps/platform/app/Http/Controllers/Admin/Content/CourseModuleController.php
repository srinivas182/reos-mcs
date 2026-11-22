<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ModuleRequest;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\User;
use App\Services\Content\Courses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** A course's modules (A5.3): add, rename, remove (its lessons stay in the course). */
final class CourseModuleController extends Controller
{
    public function store(ModuleRequest $request, Course $course, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->addModule($course, $request->string('title')->toString(), $actor);

        return back();
    }

    public function update(ModuleRequest $request, CourseModule $module, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->renameModule($module, $request->string('title')->toString(), $actor);

        return back();
    }

    public function destroy(CourseModule $module, #[CurrentUser] User $actor, Courses $courses): RedirectResponse
    {
        $courses->removeModule($module, $actor);

        return back();
    }
}
