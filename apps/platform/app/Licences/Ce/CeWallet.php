<?php

declare(strict_types=1);

namespace App\Licences\Ce;

use App\Billing\Buyer;
use App\Billing\Entitlements;
use App\Billing\ProductStatus;
use App\Content\CourseType;
use App\Enums\LicenceStatus;
use App\Licences\TrecLicenceLookup;
use App\Models\CeCompletion;
use App\Models\Licence;
use App\Models\LicenceRenewal;
use App\Models\Price;
use App\Models\User;
use App\Services\StateRules\CeRequirements;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The CE wallet (A11.2, ADR 0045; agent screens 103, 105, 106 and Me → CE
 * wallet): for each licence, hours done against the renewal (required and
 * electives), every completion in the period and who reported it, TREC's
 * own view from the nightly file, the renewal checklist, and where to get
 * the hours still missing.
 *
 * A completion is **REOS-reported** when it's a REOS course, **self-reported**
 * when it came from another provider (B10.3b) until TREC's record confirms
 * it, then **on TREC's record**.
 *
 * @phpstan-import-type Requirement from CeRequirements
 *
 * @phpstan-type Completion array{id: int, title: string, hours: float, completed_on: string, provider: string|null, course_code: string|null, status: 'reos_reported'|'self_reported'|'on_record', certificate: bool}
 * @phpstan-type Step array{code: string, title: string, detail: string, url: string|null, done: bool}
 * @phpstan-type Wallet array{licence: array{id: int, state_code: string, state_name: string, number: string, type: string, status: string, expires_on: string|null, sponsor_name: string|null}, ce: Requirement, completions: list<Completion>, trec: array{mce: 'met'|'outstanding'|'none'|null, education: 'met'|'outstanding'|'none'|null, checked_at: string|null}, renewal: array{due_on: string|null, ready: bool, renewed_at: string|null, confirmed_at: string|null, renewed_until: string|null, steps: list<Step>}, buy: array{price: int, product: string, amount: int, interval: string}|null, unlocked: bool}
 */
final readonly class CeWallet
{
    public const STATUS_CODES = [0 => 'none', 1 => 'outstanding', 2 => 'met'];

    public function __construct(
        private CeRequirements $requirements,
        private StateRules $rules,
        private Entitlements $entitlements,
    ) {}

    /** @return list<Wallet> */
    public function for(User $user, ?CarbonImmutable $today = null): array
    {
        $wallets = [];

        foreach (Licence::query()->with('state')->where('user_id', $user->id)->orderBy('state_code')->get() as $licence) {
            $wallet = $this->wallet($licence, $today);

            if ($wallet !== null) {
                $wallets[] = $wallet;
            }
        }

        return $wallets;
    }

    /** @return Wallet|null Null when the licence's state has no CE rules. */
    public function wallet(Licence $licence, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();
        $ce = $this->requirements->for($licence, $today);

        if ($ce === null) {
            return null;
        }

        $completions = $this->completions($licence, $ce);
        $record = $licence->state_code === 'TX' ? TrecLicenceLookup::record($licence->number) : null;
        $trec = [
            'mce' => $record?->mce_status === null ? null : (self::STATUS_CODES[$record->mce_status] ?? null),
            'education' => $record?->education_status === null ? null : (self::STATUS_CODES[$record->education_status] ?? null),
            'checked_at' => $record?->imported_at->toIso8601String(),
        ];
        $renewal = $ce['due_on'] === null ? null : LicenceRenewal::query()->where('licence_id', $licence->id)->where('due_on', $ce['due_on'])->first();
        $features = $this->entitlements->features($licence->user, $licence->state_code);
        $unlocked = in_array(Entitlements::courseFeature(CourseType::Ce), $features, true);

        return [
            'licence' => [
                'id' => $licence->id, 'state_code' => $licence->state_code, 'state_name' => $licence->state->name, 'number' => $licence->number,
                'type' => $licence->type->value, 'status' => $licence->status->value, 'expires_on' => $licence->expires_on?->toDateString(), 'sponsor_name' => $licence->sponsor_name,
            ],
            'ce' => $ce,
            'completions' => $completions,
            'trec' => $trec,
            'renewal' => [
                'due_on' => $ce['due_on'],
                'ready' => $ce['complete'],
                'renewed_at' => $renewal?->renewed_at?->toIso8601String(),
                'confirmed_at' => $renewal?->confirmed_at?->toIso8601String(),
                'renewed_until' => $renewal?->renewed_until === null ? null : substr($renewal->renewed_until, 0, 10),
                'steps' => $this->steps($licence, $ce, $completions, $trec, $renewal),
            ],
            'buy' => $unlocked || $ce['complete'] ? null : $this->price($licence->state_code),
            'unlocked' => $unlocked,
        ];
    }

    /**
     * The agent says they renewed with the regulator (or takes it back).
     *
     * @return Wallet|null
     */
    public function renewed(Licence $licence, bool $renewed): ?array
    {
        $ce = $this->requirements->for($licence);

        if ($ce === null || $ce['due_on'] === null) {
            throw ValidationException::withMessages(['licence' => self::line('ce.wallet.errors.noRenewal')]);
        }

        LicenceRenewal::query()->updateOrCreate(['licence_id' => $licence->id, 'due_on' => $ce['due_on']], ['renewed_at' => $renewed ? now() : null]);

        return $this->wallet($licence);
    }

    /**
     * @param  Requirement  $ce
     * @return list<Completion>
     */
    private function completions(Licence $licence, array $ce): array
    {
        $since = $ce['period_start'];

        $list = [];
        $rows = CeCompletion::query()
            ->where('user_id', $licence->user_id)->where('state_code', $licence->state_code)
            ->when($since !== null, static fn ($q) => $q->whereDate('completed_on', '>', $since))
            ->orderByDesc('completed_on')->orderByDesc('id')->get();

        foreach ($rows as $c) {
            $list[] = [
                'id' => $c->id, 'title' => $c->title, 'hours' => (float) $c->hours, 'completed_on' => $c->completed_on->toDateString(),
                'provider' => $c->provider, 'course_code' => $c->course_code, 'status' => self::status($c), 'certificate' => $c->certificate_path !== null,
            ];
        }

        return $list;
    }

    /**
     * The renewal checklist from the state's rules, each step marked done.
     *
     * @param  Requirement  $ce
     * @param  list<Completion>  $completions
     * @param  array{mce: string|null, education: string|null, checked_at: string|null}  $trec
     * @return list<Step>
     */
    private function steps(Licence $licence, array $ce, array $completions, array $trec, ?LicenceRenewal $renewal): array
    {
        $rule = $this->rules->inForce($licence->state_code, CarbonImmutable::today())['ce.renewal_steps'] ?? null;
        $steps = $rule?->value['steps'] ?? [];
        $requirementMet = $trec[$ce['first_renewal'] ? 'education' : 'mce'] === 'met';
        $selfReported = array_filter($completions, static fn (array $c): bool => $c['status'] === 'self_reported') !== [];
        $list = [];

        foreach (is_array($steps) ? $steps : [] as $step) {
            if (! is_array($step) || ! is_string($step['code'] ?? null)) {
                continue;
            }

            $done = match ($step['code']) {
                'ce_reported' => $requirementMet || ($ce['complete'] && ! $selfReported),
                'sponsor' => $licence->sponsor_name !== null && $licence->status === LicenceStatus::Active,
                'renew' => $renewal?->renewed_at !== null || $renewal?->confirmed_at !== null,
                'confirm' => $renewal?->confirmed_at !== null,
                default => false,
            };
            $list[] = [
                'code' => $step['code'], 'title' => is_string($step['title'] ?? null) ? $step['title'] : $step['code'],
                'detail' => is_string($step['detail'] ?? null) ? $step['detail'] : '', 'url' => is_string($step['url'] ?? null) ? $step['url'] : null, 'done' => $done,
            ];
        }

        return $list;
    }

    /** @return array{price: int, product: string, amount: int, interval: string}|null The cheapest live agent plan that unlocks CE courses in the state. */
    private function price(string $state): ?array
    {
        $feature = Entitlements::courseFeature(CourseType::Ce);
        $price = Price::query()->with('product')->current()->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', Buyer::Agent)->where('status', ProductStatus::Live)->whereJsonContains('features', $feature))
            ->orderBy('amount_cents')->first();

        return $price === null ? null : ['price' => $price->id, 'product' => $price->product->name, 'amount' => $price->amount_cents, 'interval' => $price->interval->value];
    }

    /** @return 'reos_reported'|'self_reported'|'on_record' */
    public static function status(CeCompletion $completion): string
    {
        return match (true) {
            $completion->confirmed_at !== null => 'on_record',
            $completion->source === 'reos' => 'reos_reported',
            default => 'self_reported',
        };
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
