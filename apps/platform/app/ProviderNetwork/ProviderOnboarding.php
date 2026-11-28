<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Features\Flags;
use App\Licences\ProviderLicences;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProviderCategory;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantSignups;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Joining the provider network in about ten minutes (B15.2, provider screens
 * 1–6, ADR 0074): welcome, category (B15.1), licence or business details
 * and an ID, service areas, a plan bought on reos.com, and the network
 * rules. The profile goes live when every step is done, the licence is
 * verified, and the network is open in the state (decision 5: the
 * `provider_network` flag, off until the RESPA review is signed off).
 *
 * A live profile is hidden when its licence stops being verified (the
 * monthly re-check, or a reviewer), and comes back when it's fixed.
 *
 * @phpstan-import-type Joining from ProviderJoining
 * @phpstan-import-type AreaRow from ServiceAreas
 * @phpstan-import-type Plan from ProviderPlans
 * @phpstan-import-type ProviderLicenceRow from ProviderLicences
 *
 * @phpstan-type Step array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}
 * @phpstan-type Onboarding array{status: string, hidden_reason: string|null, steps: list<Step>, done: int, joining: Joining, licence: array{kind: string, licences: list<ProviderLicenceRow>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<AreaRow>, plans: list<Plan>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}
 */
final readonly class ProviderOnboarding
{
    public const STEPS = ['welcome', 'category', 'licence', 'areas', 'plan', 'rules'];

    public function __construct(
        private ProviderJoining $joining,
        private ServiceAreas $areas,
        private ProviderPlans $plans,
        private ProviderLicences $licences,
        private TenantSignups $signups,
        private Flags $flags,
        private AuditLogger $audit,
    ) {}

    public function profile(User $user): ProviderProfile
    {
        return ProviderProfile::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** @return Onboarding */
    public function status(User $user): array
    {
        // The network may have opened in the state since the last step (decision 5).
        $this->refresh($user);
        $profile = $this->profile($user)->load('category');
        $category = $profile->category;
        $kind = self::licenceKind($category);
        $licences = $this->licences->rowsFor($user);
        $steps = [];

        foreach ($this->done($profile) as $key => $done) {
            $steps[] = ['key' => $key, 'done' => $done];
        }

        return [
            'status' => $profile->status, 'hidden_reason' => $profile->hidden_reason, 'steps' => $steps,
            'done' => count(array_filter($steps, static fn (array $s): bool => $s['done'])),
            'joining' => $this->joining->profile($user),
            'licence' => [
                'kind' => $kind, 'licences' => $licences,
                'business' => $kind === 'business' ? ['name' => $profile->business_name, 'address' => $profile->business_address] : null,
                'verified' => $this->licenceVerified($profile),
            ],
            'areas' => $this->areas->of($profile),
            'plans' => $category === null ? [] : $this->plans->for($category, $profile->state_code),
            'tier' => $profile->tier,
            'rules' => ['version' => NetworkRules::VERSION, 'keys' => NetworkRules::RULES, 'accepted_at' => $profile->rules_accepted_at?->toIso8601String(), 'current' => $profile->rules_version === NetworkRules::VERSION],
        ];
    }

    /** @return Onboarding */
    public function welcome(User $user): array
    {
        $profile = $this->profile($user);
        $profile->welcomed_at ??= Carbon::now();
        $profile->save();

        return $this->status($user);
    }

    /**
     * Business details and a photo ID, for a category without a licence.
     *
     * @return Onboarding
     */
    public function business(User $user, string $name, string $address, ?UploadedFile $id): array
    {
        $profile = $this->profile($user)->load('category');

        if (self::licenceKind($profile->category) !== 'business') {
            throw ValidationException::withMessages(['category' => __('providers.onboarding.errors.licenceNeeded')]);
        }

        $profile->forceFill(['business_name' => trim($name), 'business_address' => trim($address)])->save();
        $existing = ProviderLicence::query()->where('user_id', $user->id)->where('kind', 'business_id')->first();

        if ($id === null && $existing === null) {
            throw ValidationException::withMessages(['id_photo' => __('providers.onboarding.errors.idPhoto')]);
        }

        if ($id !== null) {
            $disk = config()->string('reos.content.disk');
            $path = $id->storeAs("provider-ids/{$user->id}", Str::uuid7().'.'.($id->guessExtension() ?? 'jpg'), $disk);

            if ($existing?->document_path !== null) {
                Storage::disk($disk)->delete($existing->document_path);
            }

            ProviderLicence::query()->updateOrCreate(['user_id' => $user->id, 'kind' => 'business_id'], [
                'number' => mb_substr(trim($name), 0, 40), 'status' => ProviderLicence::REVIEW, 'checked_via' => 'reviewer', 'document_path' => $path ?: null,
                'review_note' => __('providers.onboarding.business.reviewNote'), 'reviewed_by' => null, 'reviewed_at' => null, 'name_on_record' => null, 'licence_type' => null, 'expires_on' => null,
            ]);
        }

        $this->refresh($user);

        return $this->status($user);
    }

    /**
     * @param  'city'|'zip'|'radius'  $kind
     * @return Onboarding
     */
    public function addArea(User $user, string $kind, ?string $city, ?string $zip, ?int $radius): array
    {
        $this->areas->add($this->profile($user), $kind, $city, $zip, $radius);
        $this->refresh($user);

        return $this->status($user);
    }

    /** @return Onboarding */
    public function removeArea(User $user, int $id): array
    {
        $this->areas->remove($this->profile($user), $id);
        $this->refresh($user);

        return $this->status($user);
    }

    /**
     * Starts paying for a plan on reos.com; returns the checkout page.
     *
     * @return array{checkout_url: string}
     */
    public function checkout(User $user, string $tier): array
    {
        $profile = $this->profile($user)->load('category');
        $category = $profile->category;

        if ($category === null) {
            throw ValidationException::withMessages(['category' => __('providers.join.errors.chooseFirst')]);
        }

        $areas = $profile->areas()->count();

        if ($areas === 0) {
            throw ValidationException::withMessages(['tier' => __('providers.onboarding.plan.errors.areasFirst')]);
        }

        if ($areas > ProviderPlans::areas($category, $tier)) {
            throw ValidationException::withMessages(['tier' => __('providers.onboarding.plan.errors.tooManyAreas', ['count' => ProviderPlans::areas($category, $tier)])]);
        }

        $price = $this->plans->price($category, $tier, $profile->state_code);

        if ($price === null) {
            throw ValidationException::withMessages(['tier' => __('providers.onboarding.plan.errors.notPriced')]);
        }

        $name = $profile->business_name ?? $user->name ?? $category->name;
        $signup = $this->signups->start($user, 'provider', (string) $name, [$profile->state_code], $price);

        return ['checkout_url' => route('checkout.show', ['price' => $price->id, 'signup' => $signup->uuid])];
    }

    /** The plan is paid (TenantSignups::complete): the provider business and its tier. */
    public function planBought(User $user, Organisation $business, Product $product): void
    {
        $profile = $this->profile($user);
        $profile->forceFill(['organisation_id' => $business->id, 'tier' => ProviderPlans::tierOf($product) ?? 'basic'])->save();
        $this->refresh($user);
    }

    /** @return Onboarding */
    public function acceptRules(User $user, string $version): array
    {
        if ($version !== NetworkRules::VERSION) {
            throw ValidationException::withMessages(['version' => __('providers.onboarding.rules.changed')]);
        }

        $profile = $this->profile($user);
        $profile->forceFill(['rules_version' => NetworkRules::VERSION, 'rules_accepted_at' => now()])->save();
        $this->audit->record('provider.rules_accepted', 'user', (string) $user->id, ['version' => NetworkRules::VERSION], $user);
        $this->refresh($user);

        return $this->status($user);
    }

    /**
     * Live, waiting, joining or hidden, from the steps and the licence.
     * Called after every step, a reviewer's decision and the monthly re-check.
     */
    public function refresh(User $user): void
    {
        $profile = ProviderProfile::query()->with(['category', 'user'])->where('user_id', $user->id)->first();

        if ($profile === null) {
            return;
        }

        $complete = ! in_array(false, $this->done($profile), true);
        $verified = $this->licenceVerified($profile);
        $before = $profile->status;

        [$status, $reason] = match (true) {
            $complete && $verified && $this->open($profile) => [ProviderProfile::LIVE, null],
            $complete && $verified => [ProviderProfile::WAITING, null],
            $profile->live_at !== null && ! $verified => [ProviderProfile::HIDDEN, $this->hiddenReason($profile)],
            default => [ProviderProfile::JOINING, null],
        };

        if ($before === $status && $profile->hidden_reason === $reason) {
            return;
        }

        $profile->forceFill(['status' => $status, 'hidden_reason' => $reason, 'checked_at' => now(), ...($status === ProviderProfile::LIVE ? ['live_at' => $profile->live_at ?? now()] : [])])->save();

        if ($before !== $status) {
            $this->audit->record('provider.'.$status, 'user', (string) $user->id, ['before' => $before, 'reason' => $reason], null, $profile->organisation_id);
        }
    }

    /** @return array<'welcome'|'category'|'licence'|'areas'|'plan'|'rules', bool> */
    private function done(ProviderProfile $profile): array
    {
        $kind = self::licenceKind($profile->category);
        $licence = $kind === 'business'
            ? $profile->business_name !== null && ProviderLicence::query()->where('user_id', $profile->user_id)->where('kind', 'business_id')->whereIn('status', [ProviderLicence::REVIEW, ProviderLicence::VERIFIED])->exists()
            : ProviderLicence::query()->where('user_id', $profile->user_id)->where('kind', $kind)->whereIn('status', [ProviderLicence::REVIEW, ProviderLicence::VERIFIED])->exists();

        return [
            'welcome' => $profile->welcomed_at !== null,
            'category' => $profile->category !== null && $profile->category->status === 'live',
            'licence' => $profile->category !== null && $licence,
            'areas' => $profile->areas()->exists(),
            'plan' => $profile->organisation_id !== null && $profile->tier !== null,
            'rules' => $profile->rules_version === NetworkRules::VERSION,
        ];
    }

    private function licenceVerified(ProviderProfile $profile): bool
    {
        if ($profile->category === null) {
            return false;
        }

        $kind = self::licenceKind($profile->category);
        $licence = ProviderLicence::query()->where('user_id', $profile->user_id)->where('kind', $kind === 'business' ? 'business_id' : $kind)->first();

        return $licence !== null && $licence->status === ProviderLicence::VERIFIED && ($licence->expires_on === null || ! $licence->expires_on->isPast());
    }

    private function hiddenReason(ProviderProfile $profile): string
    {
        $kind = self::licenceKind($profile->category);
        $licence = ProviderLicence::query()->where('user_id', $profile->user_id)->where('kind', $kind === 'business' ? 'business_id' : $kind)->first();

        return match (true) {
            $licence === null => 'licence_missing',
            $licence->expires_on !== null && $licence->expires_on->isPast() => 'licence_expired',
            $licence->status === ProviderLicence::REJECTED => 'licence_rejected',
            $licence->status === ProviderLicence::REVIEW => 'licence_review',
            default => 'licence_inactive',
        };
    }

    /** The network is open in the provider's state (decision 5). */
    private function open(ProviderProfile $profile): bool
    {
        $business = $profile->organisation_id === null ? null : Organisation::query()->find($profile->organisation_id);

        return $business !== null && $this->flags->forTenant($business, 'provider_network');
    }

    /** nmls, trec_inspector, tdi, or business (details and a photo ID). */
    public static function licenceKind(?ProviderCategory $category): string
    {
        return match ($category?->licence_kind) {
            'nmls' => 'nmls',
            'trec_inspector' => 'trec_inspector',
            'tdi' => 'tdi',
            default => 'business',
        };
    }
}
