<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Consent records (A6.3): giving and withdrawing consent, and the check
 * every feature makes before acting on one ("may REOS record this session,
 * text this person, use their data for AI help?"). The latest record for a
 * person, purpose and subject wins; without one, only the purpose's
 * default applies (see `ConsentPurpose::defaultFor`).
 */
final readonly class Consents
{
    public function __construct(
        private Request $request,
        private ActiveRole $roles,
        private AuditLogger $audit,
    ) {}

    public function grant(User $user, ConsentPurpose $purpose, ConsentMethod $method, ?string $subject = null, ?User $actor = null, ?string $note = null): ConsentRecord
    {
        return $this->record($user, $purpose, true, $method, $subject, $actor, $note);
    }

    public function withdraw(User $user, ConsentPurpose $purpose, ConsentMethod $method, ?string $subject = null, ?User $actor = null, ?string $note = null): ConsentRecord
    {
        return $this->record($user, $purpose, false, $method, $subject, $actor, $note);
    }

    /** Signing up agrees to the terms and the privacy notice in force (every sign-up path). */
    public function signedUp(User $user): void
    {
        $method = $this->method();
        $this->grant($user, ConsentPurpose::Terms, $method);
        $this->grant($user, ConsentPurpose::PrivacyNotice, $method);
    }

    /** Whether REOS may act on this purpose for the person now. */
    public function allows(User $user, ConsentPurpose $purpose, ?string $subject = null): bool
    {
        $latest = $this->latest($user, $purpose, $subject);

        if ($latest !== null) {
            return $latest->granted;
        }

        return ! $purpose->perSubject() && ! $purpose->required() && $purpose->defaultFor($this->roles->for($user)?->role);
    }

    public function latest(User $user, ConsentPurpose $purpose, ?string $subject = null): ?ConsentRecord
    {
        return ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->when($subject === null, static fn ($q) => $q->whereNull('subject'), static fn ($q) => $q->where('subject', $subject))
            ->latest('id')
            ->first();
    }

    /**
     * The privacy settings: each switch (on or off, and whether that's the
     * default or their choice) and every consent record, newest first.
     *
     * @return array{switches: list<array{purpose: string, on: bool, chosen: bool, at: string|null}>, records: list<array{id: int, purpose: string, subject: string|null, granted: bool, version: string, method: string, by_admin: bool, at: string, current: bool, withdrawable: bool}>}
     */
    public function summary(User $user): array
    {
        $role = $this->roles->for($user)?->role;
        $records = ConsentRecord::query()->where('user_id', $user->id)->orderByDesc('id')->limit(200)->get();
        $latestIds = [];

        foreach ($records as $record) {
            $key = $record->purpose->value.'|'.($record->subject ?? '');
            $latestIds[$key] ??= $record->id;
        }

        return [
            'switches' => array_map(function (ConsentPurpose $purpose) use ($records, $role): array {
                $latest = $records->first(static fn (ConsentRecord $r): bool => $r->purpose === $purpose && $r->subject === null);

                return [
                    'purpose' => $purpose->value,
                    'on' => $latest !== null ? $latest->granted : $purpose->defaultFor($role),
                    'chosen' => $latest !== null,
                    'at' => $latest?->created_at->toIso8601String(),
                ];
            }, ConsentPurpose::switches()),
            'records' => array_values($records->map(static function (ConsentRecord $record) use ($latestIds): array {
                $current = ($latestIds[$record->purpose->value.'|'.($record->subject ?? '')] ?? null) === $record->id;

                return [
                    'id' => $record->id,
                    'purpose' => $record->purpose->value,
                    'subject' => $record->subject,
                    'granted' => $record->granted,
                    'version' => $record->version,
                    'method' => $record->method->value,
                    'by_admin' => $record->actor_id !== null,
                    'at' => $record->created_at->toIso8601String(),
                    'current' => $current,
                    // Required ones go only with the account.
                    'withdrawable' => $current && $record->granted && ! $record->purpose->required(),
                ];
            })->all()),
        ];
    }

    /** Web or app, from the request that's asking. */
    public function method(): ConsentMethod
    {
        return $this->request->is('api/*') ? ConsentMethod::App : ConsentMethod::Web;
    }

    private function record(User $user, ConsentPurpose $purpose, bool $granted, ConsentMethod $method, ?string $subject, ?User $actor, ?string $note): ConsentRecord
    {
        $record = ConsentRecord::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'subject' => $subject,
            'granted' => $granted,
            'version' => $purpose->version(),
            'method' => $actor !== null && $actor->id !== $user->id ? ConsentMethod::Admin : $method,
            'actor_id' => $actor !== null && $actor->id !== $user->id ? $actor->id : null,
            'note' => $note === null ? null : Str::limit($note, 300, ''),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 300, ''),
        ]);

        // An admin acting for someone is in the audit log too; people's own choices are in their records.
        if ($record->actor_id !== null && $actor !== null) {
            $this->audit->record('privacy.consent_changed', 'user', (string) $user->id, [
                'purpose' => $purpose->value, 'subject' => $subject, 'from' => $granted ? 'withdrawn' : 'granted', 'to' => $granted ? 'granted' : 'withdrawn',
            ], $actor);
        }

        return $record;
    }
}
