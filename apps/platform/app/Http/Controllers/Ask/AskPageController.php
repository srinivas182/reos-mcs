<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ask;

use App\Ask\Ask;
use App\Ask\AskPackage;
use App\Content\Learning\Learning;
use App\Http\Controllers\Controller;
use App\Models\AskThread;
use App\Models\State;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The web Ask page (A10.1): conversations on the left, the open one beside them. */
final class AskPageController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, AskPackage $package, Learning $learning): Response
    {
        abort_unless($user->can('learning.study'), 403);
        $open = $request->query('thread');
        $thread = is_string($open) ? AskThread::query()->where('user_id', $user->id)->where('uuid', $open)->first() : null;

        $code = $learning->state($user);

        return Inertia::render('ask/index', [
            'state' => State::query()->where('code', $code)->value('name') ?? $code,
            'threads' => AskThread::query()->where('user_id', $user->id)->latest('updated_at')->latest('id')->limit(30)->get()
                ->map(static fn (AskThread $t): array => ['uuid' => $t->uuid, 'title' => $t->title, 'updated_at' => $t->updated_at?->toIso8601String()])->values()->all(),
            'thread' => $thread === null ? null : Ask::thread($thread),
            'package' => $package->for($user),
        ]);
    }
}
