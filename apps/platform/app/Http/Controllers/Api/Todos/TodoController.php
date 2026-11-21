<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Todos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Todos\SaveTodoRequest;
use App\Http\Resources\Todos\TodoResource;
use App\Models\Todo;
use App\Models\User;
use App\Services\Todos\Todos;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Your to-dos (B4.3, ADR 0018): typed, or spoken and confirmed. A due date
 * is also an event on your calendar; reminders ring on the phone.
 */
final class TodoController extends Controller
{
    /**
     * List to-dos.
     *
     * Open ones (the default) by due date, undated last; `status=done` for
     * finished ones, newest first; `status=all` for both.
     */
    public function index(Request $request, #[CurrentUser] User $user, Todos $todos): AnonymousResourceCollection
    {
        $status = $request->query('status');

        return TodoResource::collection($todos->list($user, in_array($status, ['open', 'done', 'all'], true) ? $status : 'open'));
    }

    /**
     * Add a to-do.
     *
     * Send the phone's own `client_id`: sending the same one again returns
     * the to-do already saved (200) instead of adding another (201).
     *
     * @status 201
     */
    public function store(SaveTodoRequest $request, #[CurrentUser] User $user, Todos $todos): TodoResource
    {
        // A new model answers 201 and one already saved 200 (Laravel reads `wasRecentlyCreated`).
        [$todo] = $todos->create($user, $request->values(), $request->clientId());

        return new TodoResource($todo);
    }

    /** Change a to-do. */
    public function update(SaveTodoRequest $request, #[CurrentUser] User $user, int $todo, Todos $todos): TodoResource
    {
        return new TodoResource($todos->update($this->find($user, $todo), $request->values()));
    }

    /** Mark done (from the app or the reminder's Done button). */
    public function done(#[CurrentUser] User $user, int $todo, Todos $todos): TodoResource
    {
        return new TodoResource($todos->complete($this->find($user, $todo)));
    }

    /** Mark not done (Undo). */
    public function undone(#[CurrentUser] User $user, int $todo, Todos $todos): TodoResource
    {
        return new TodoResource($todos->complete($this->find($user, $todo), false));
    }

    /** Delete a to-do (restore undoes it). */
    public function destroy(#[CurrentUser] User $user, int $todo, Todos $todos): Response
    {
        $todos->delete($this->find($user, $todo));

        return response()->noContent();
    }

    /** Restore a deleted to-do. */
    public function restore(#[CurrentUser] User $user, int $todo, Todos $todos): TodoResource
    {
        $found = $this->find($user, $todo, trashed: true);
        abort_unless($found->trashed(), 404);

        return new TodoResource($todos->restore($found));
    }

    private function find(User $user, int $id, bool $trashed = false): Todo
    {
        return ($trashed ? Todo::withTrashed() : Todo::query())->where('user_id', $user->id)->findOrFail($id);
    }
}
