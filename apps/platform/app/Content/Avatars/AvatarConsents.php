<?php

declare(strict_types=1);

namespace App\Content\Avatars;

use App\Authorization\Role;
use App\Models\AvatarConsent;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AvatarConsentEnded;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Content\LessonFiles;
use App\Services\Privacy\Consents;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Instructor avatar consent (A7.1b, ADR 0025; Instructor journey: avatar
 * consent, Record 2-minute avatar sample; school admin screen 52).
 *
 * An instructor agrees to the template (its version is recorded as a
 * consent record for that school) and gives a 2-minute sample the avatar is
 * made from. The avatar is used only by that school and only for lessons the
 * instructor approves. When consent ends (the instructor withdraws, or
 * stops teaching there) the sample is deleted and the school has 30 days to
 * unpublish or re-render lessons that use the avatar; its admins are told.
 */
final readonly class AvatarConsents
{
    private const AUDIT_SUBJECT = 'avatar_consent';

    public function __construct(
        private Consents $consents,
        private Memberships $memberships,
        private AuditLogger $audit,
    ) {}

    public function for(User $user, Organisation $school): ?AvatarConsent
    {
        return AvatarConsent::query()->where('user_id', $user->id)->where('organisation_id', $school->id)->first();
    }

    /** Whether the school may use (or make) this instructor's avatar now. */
    public function usable(User $user, Organisation $school): bool
    {
        $consent = $this->for($user, $school);

        return $consent !== null && $consent->status === AvatarConsent::GIVEN
            && $consent->version === ConsentPurpose::AvatarLikeness->version()
            && $this->consents->allows($user, ConsentPurpose::AvatarLikeness, AvatarConsent::subject($school));
    }

    /** The instructor agrees to the template and gives the sample. */
    public function give(User $user, Organisation $school, UploadedFile $sample, ConsentMethod $method): AvatarConsent
    {
        if ($this->memberships->find($user, Role::Instructor, $school->id) === null) {
            throw ValidationException::withMessages(['sample' => __('avatarConsent.errors.notInstructor')]);
        }

        $existing = $this->for($user, $school);
        $uuid = $existing->uuid ?? (string) Str::uuid();
        $extension = $sample->guessExtension() ?: 'mp4';
        $path = "tenants/{$school->id}/avatars/{$uuid}.{$extension}";
        LessonFiles::disk()->putFileAs(dirname($path), $sample, basename($path));

        if ($existing?->sample_path !== null && $existing->sample_path !== $path) {
            LessonFiles::disk()->delete($existing->sample_path);
        }

        return DB::transaction(function () use ($user, $school, $sample, $method, $uuid, $path, $existing): AvatarConsent {
            $record = $this->consents->grant($user, ConsentPurpose::AvatarLikeness, $method, AvatarConsent::subject($school));
            $consent = $existing ?? new AvatarConsent(['uuid' => $uuid, 'user_id' => $user->id, 'organisation_id' => $school->id]);
            $consent->fill([
                'status' => AvatarConsent::GIVEN, 'version' => ConsentPurpose::AvatarLikeness->version(), 'consent_record_id' => $record->id,
                'sample_path' => $path, 'sample_mime' => $sample->getMimeType(), 'sample_size' => $sample->getSize(),
                'given_at' => now(), 'withdrawn_at' => null, 'lessons_due_by' => null,
            ])->save();

            $this->audit->record('avatar.consent_given', self::AUDIT_SUBJECT, $consent->uuid, ['version' => $consent->version], $user, $school->id);

            return $consent;
        });
    }

    /** The instructor withdraws: recorded, the sample deleted, the school told. */
    public function withdraw(User $user, Organisation $school, ConsentMethod $method): AvatarConsent
    {
        $consent = $this->for($user, $school);

        if ($consent === null || $consent->status !== AvatarConsent::GIVEN) {
            throw ValidationException::withMessages(['consent' => __('avatarConsent.errors.notGiven')]);
        }

        $this->consents->withdraw($user, ConsentPurpose::AvatarLikeness, $method, AvatarConsent::subject($school));

        return $this->end($consent, AvatarConsent::WITHDRAWN, $user);
    }

    /**
     * The school stops using the avatar because the person no longer teaches
     * there (Revoke on screen 52). Their consent record stays theirs.
     */
    public function endFor(User $user, Organisation $school, User $actor): ?AvatarConsent
    {
        $consent = $this->for($user, $school);

        return $consent === null || $consent->status !== AvatarConsent::GIVEN ? $consent : $this->end($consent, AvatarConsent::ENDED, $actor, keepSample: true);
    }

    /** Undo of Revoke on screen 52: the avatar is usable again while its sample is still there. */
    public function resume(User $user, Organisation $school): void
    {
        $consent = $this->for($user, $school);

        if ($consent?->status === AvatarConsent::ENDED && $consent->sample_path !== null && $this->consents->allows($user, ConsentPurpose::AvatarLikeness, AvatarConsent::subject($school))) {
            $consent->fill(['status' => AvatarConsent::GIVEN, 'withdrawn_at' => null, 'lessons_due_by' => null])->save();
        }
    }

    /**
     * Deletes the samples of avatars that ended more than a day ago (kept
     * that long only so Revoke can be undone). Daily, with the approvals check.
     */
    public function prune(): int
    {
        $count = 0;

        AvatarConsent::query()->withoutGlobalScopes()->where('status', '!=', AvatarConsent::GIVEN)->whereNotNull('sample_path')
            ->where('withdrawn_at', '<', now()->subDay())->each(static function (AvatarConsent $consent) use (&$count): void {
                LessonFiles::disk()->delete((string) $consent->sample_path);
                $consent->fill(['sample_path' => null, 'sample_mime' => null, 'sample_size' => null])->save();
                $count++;
            });

        return $count;
    }

    /** A signed link to play the sample (the instructor and the school's admins). */
    public function sampleUrl(AvatarConsent $consent): ?string
    {
        if ($consent->sample_path === null) {
            return null;
        }

        $disk = LessonFiles::disk();

        return $disk->getAdapter() instanceof LocalFilesystemAdapter
            ? URL::temporarySignedRoute('avatar.sample', now()->addMinutes(30), ['consent' => $consent->uuid])
            : $disk->temporaryUrl($consent->sample_path, now()->addMinutes(30), ['ResponseContentType' => $consent->sample_mime ?? 'video/mp4']);
    }

    private function end(AvatarConsent $consent, string $status, User $actor, bool $keepSample = false): AvatarConsent
    {
        if (! $keepSample && $consent->sample_path !== null) {
            LessonFiles::disk()->delete($consent->sample_path);
            $consent->fill(['sample_path' => null, 'sample_mime' => null, 'sample_size' => null]);
        }

        $consent->fill([
            'status' => $status,
            'withdrawn_at' => now(), 'lessons_due_by' => CarbonImmutable::today()->addDays(AvatarConsent::WIND_DOWN_DAYS)->toDateString(),
        ])->save();

        $this->audit->record($status === AvatarConsent::WITHDRAWN ? 'avatar.consent_withdrawn' : 'avatar.consent_ended', self::AUDIT_SUBJECT, $consent->uuid, [
            'lessons_due_by' => $consent->lessons_due_by?->toDateString(),
        ], $actor, $consent->organisation_id);

        Notification::send($this->memberships->holdersIn(Role::SchoolAdmin, $consent->organisation), new AvatarConsentEnded($consent));

        return $consent;
    }
}
