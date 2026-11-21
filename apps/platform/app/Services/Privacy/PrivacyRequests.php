<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\PrivacyRequest;
use App\Models\User;
use App\Notifications\PrivacyRequestUpdated;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Privacy requests under the Texas Data Privacy and Security Act (A4.3b,
 * screen 71). People ask from Settings › Profile; REOS answers within 45
 * days (once extendable by 45 more, with notice).
 *
 * - See my data: a JSON export is prepared at once and sent as a link that
 *   works for 7 days.
 * - Correct my data: a REOS admin makes the change and answers.
 * - Delete my account: the existing 30-day deletion (AccountDeletion), on
 *   hold until then; signing in again withdraws it.
 * Every step is audited and the person is told by email.
 */
final readonly class PrivacyRequests
{
    public const AUDIT_SUBJECT = 'privacy_request';

    public const DAYS = 45;

    public const LINK_DAYS = 7;

    public function __construct(private AuditLogger $audit, private DataExport $export) {}

    public function submit(User $user, string $kind, ?string $details): PrivacyRequest
    {
        if (! in_array($kind, [PrivacyRequest::ACCESS, PrivacyRequest::CORRECT], true)) {
            throw ValidationException::withMessages(['kind' => __('privacy.errors.kind')]);
        }

        $waiting = PrivacyRequest::query()->where('user_id', $user->id)->where('kind', $kind)
            ->whereIn('status', [PrivacyRequest::OPEN, PrivacyRequest::IN_PROGRESS])->exists();

        if ($waiting) {
            throw ValidationException::withMessages(['kind' => __('privacy.errors.waiting')]);
        }

        $request = $this->create($user, $kind, $details, PrivacyRequest::OPEN);

        if ($kind === PrivacyRequest::ACCESS) {
            $this->prepareExport($request, null);
        } else {
            $this->tell($request);
        }

        return $request;
    }

    /** Account deletion asked for (AccountDeletion): on hold for its 30 days. */
    public function deletionRequested(User $user): PrivacyRequest
    {
        return $this->create($user, PrivacyRequest::DELETE, null, PrivacyRequest::HOLD);
    }

    /** Signed in again: the deletion is withdrawn. */
    public function deletionWithdrawn(User $user): void
    {
        PrivacyRequest::query()->where('user_id', $user->id)->where('kind', PrivacyRequest::DELETE)->where('status', PrivacyRequest::HOLD)
            ->update(['status' => PrivacyRequest::WITHDRAWN, 'completed_at' => now()]);
    }

    /** The account was deleted: done, and who it was is no longer kept. */
    public function deletionDone(User $user): void
    {
        PrivacyRequest::query()->where('user_id', $user->id)->update([
            'status' => PrivacyRequest::DONE,
            'completed_at' => now(),
            'name' => null,
            'email' => null,
            'details' => null,
        ]);
    }

    /** Builds the export and sends the link. Done in one step: nothing to wait for. */
    public function prepareExport(PrivacyRequest $request, ?User $actor): PrivacyRequest
    {
        $user = $request->user ?? throw ValidationException::withMessages(['request' => __('privacy.errors.noAccount')]);
        $path = "privacy/exports/{$request->id}-".bin2hex(random_bytes(8)).'.json';
        Storage::put($path, (string) json_encode($this->export->for($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->finish($request, PrivacyRequest::DONE, null, $actor, ['export_path' => $path]);
    }

    /** The 7-day link to the export, for the person who asked. */
    public function downloadLink(PrivacyRequest $request): string
    {
        return URL::temporarySignedRoute('privacy.download', ($request->completed_at ?? now())->addDays(self::LINK_DAYS), ['privacyRequest' => $request->id]);
    }

    /**
     * A REOS admin moves a request on (screen 71): start, done, decline or
     * extend (once, by 45 days, with a reason the person is told).
     */
    public function act(PrivacyRequest $request, string $action, ?string $note, User $actor): PrivacyRequest
    {
        if (! $request->waiting()) {
            throw ValidationException::withMessages(['request' => __('privacy.errors.closed')]);
        }

        return match ($action) {
            'start' => $this->move($request, ['status' => PrivacyRequest::IN_PROGRESS, 'handled_by' => $actor->id], $actor, tell: false),
            'done' => $request->kind === PrivacyRequest::ACCESS && $request->export_path === null
                ? $this->prepareExport($request, $actor)
                : $this->finish($request, PrivacyRequest::DONE, $note, $actor),
            'decline' => $this->finish($request, PrivacyRequest::DECLINED, $note ?? throw ValidationException::withMessages(['note' => __('privacy.errors.reason')]), $actor),
            'extend' => $request->extended
                ? throw ValidationException::withMessages(['request' => __('privacy.errors.extended')])
                : $this->move($request, [
                    'due_at' => $request->due_at->copy()->addDays(self::DAYS),
                    'extended' => true,
                    'response' => $note ?? throw ValidationException::withMessages(['note' => __('privacy.errors.reason')]),
                ], $actor),
            default => throw ValidationException::withMessages(['action' => __('privacy.errors.action')]),
        };
    }

    private function create(User $user, string $kind, ?string $details, string $status): PrivacyRequest
    {
        $request = PrivacyRequest::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'kind' => $kind,
            'details' => $details === null || trim($details) === '' ? null : trim($details),
            'status' => $status,
            'due_at' => now()->addDays(self::DAYS),
        ]);
        $this->audit->record('privacy.requested', self::AUDIT_SUBJECT, (string) $request->id, ['kind' => $kind], $user);

        return $request;
    }

    /** @param  array<string, mixed>  $extra */
    private function finish(PrivacyRequest $request, string $status, ?string $note, ?User $actor, array $extra = []): PrivacyRequest
    {
        return $this->move($request, [
            ...$extra,
            'status' => $status,
            'response' => $note ?? $request->response,
            'completed_at' => now(),
            'handled_by' => $actor->id ?? $request->handled_by,
        ], $actor);
    }

    /** @param  array<string, mixed>  $changes */
    private function move(PrivacyRequest $request, array $changes, ?User $actor, bool $tell = true): PrivacyRequest
    {
        $before = ['status' => $request->status, 'due_at' => $request->due_at->toDateString()];
        $request->forceFill($changes)->save();
        $this->audit->record('privacy.updated', self::AUDIT_SUBJECT, (string) $request->id, [
            'kind' => $request->kind,
            'before' => $before,
            'after' => ['status' => $request->status, 'due_at' => $request->due_at->toDateString()],
        ], $actor);

        if ($tell) {
            $this->tell($request);
        }

        return $request;
    }

    private function tell(PrivacyRequest $request): void
    {
        $user = $request->user;

        if ($user !== null && $user->email !== null) {
            $user->notify(new PrivacyRequestUpdated($request, $request->status === PrivacyRequest::DONE && $request->kind === PrivacyRequest::ACCESS ? $this->downloadLink($request) : null));
        }
    }
}
