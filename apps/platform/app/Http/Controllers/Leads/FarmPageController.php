<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Leads\FarmUpdates;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Farming market updates on the web (B14.3c): areas, this month's update, send. */
final class FarmPageController extends Controller
{
    public function index(#[CurrentUser] User $user, FarmUpdates $farm): Response
    {
        return Inertia::render('farming/index', [
            'areas' => $farm->areas($user),
            'tags' => array_values(Tag::query()->where('owner_type', 'user')->where('owner_id', $user->id)->orderBy('name')->pluck('name')->filter(static fn (mixed $v): bool => is_string($v))->all()),
        ]);
    }
}
