<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Authorization\Role;
use App\Content\Avatars\AvatarConsents;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarConsentRequest;
use App\Models\AvatarConsent;
use App\Models\Organisation;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Authorization\ActiveRole;
use App\Services\Content\LessonFiles;
use App\Tenancy\Tenancy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Avatar consent for instructors (A7.1b; Instructor journey: "avatar consent",
 * "Record 2-minute avatar sample"): the template and its version, agree with
 * a sample, see the sample, withdraw.
 */
final class AvatarController extends Controller
{
    public function edit(#[CurrentUser] User $user, ActiveRole $roles, AvatarConsents $avatars): Response
    {
        $school = $this->school($user, $roles);
        $consent = $avatars->for($user, $school);

        return Inertia::render('settings/avatar', [
            'school' => ['name' => $school->name],
            'version' => ConsentPurpose::AvatarLikeness->version(),
            'consent' => $consent === null ? null : [
                'status' => $consent->status,
                'version' => $consent->version,
                'given_at' => $consent->given_at?->toIso8601String(),
                'withdrawn_at' => $consent->withdrawn_at?->toIso8601String(),
                'lessons_due_by' => $consent->lessons_due_by?->toDateString(),
                'sample_url' => $consent->status === AvatarConsent::GIVEN ? $avatars->sampleUrl($consent) : null,
                'current' => $consent->version === ConsentPurpose::AvatarLikeness->version(),
            ],
        ]);
    }

    public function store(AvatarConsentRequest $request, #[CurrentUser] User $user, ActiveRole $roles, AvatarConsents $avatars): RedirectResponse
    {
        $sample = $request->file('sample');
        abort_unless($sample instanceof UploadedFile, 422);
        $avatars->give($user, $this->school($user, $roles), $sample, ConsentMethod::Web);

        return back()->with('success', __('avatarConsent.given'));
    }

    public function destroy(#[CurrentUser] User $user, ActiveRole $roles, AvatarConsents $avatars): RedirectResponse
    {
        $avatars->withdraw($user, $this->school($user, $roles), ConsentMethod::Web);

        return back()->with('success', __('avatarConsent.withdrawn'));
    }

    /** The sample, through a 30-minute signed link (local storage; S3 uses its own signed link). */
    public function sample(string $consent, Tenancy $tenancy): BinaryFileResponse
    {
        // The signed link is the permission; the row is read whatever tenant is in use.
        $found = $tenancy->asPlatform(static fn (): ?AvatarConsent => AvatarConsent::query()->withoutGlobalScopes()->where('uuid', $consent)->first());
        $disk = LessonFiles::disk();
        abort_unless($found !== null && $found->sample_path !== null && $disk->exists($found->sample_path), 404);

        return response()->file($disk->path($found->sample_path), [
            'Content-Type' => $found->sample_mime ?? 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::Instructor && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }
}
