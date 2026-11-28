<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\User;
use App\Services\Tenancy\Seats;
use Illuminate\Support\Facades\DB;

/**
 * The brokerage's setup checklist (A15.1a, broker journey screens 1–6):
 * from the TREC check to live in about 15 minutes. Each step says whether
 * it's done and where to do it; the checklist shows until every step is.
 *
 * @phpstan-type Step array{key: 'verify'|'agents'|'seats'|'manual'|'advertising'|'mentors', done: bool, href: string}
 * @phpstan-type Setup array{trec_number: string|null, done: int, total: int, steps: list<Step>}
 */
final readonly class BrokerageSetup
{
    public function __construct(private Seats $seats) {}

    /** @return Setup|null null when the place isn't a brokerage */
    public function for(Organisation $brokerage): ?array
    {
        if ($brokerage->type !== OrganisationType::Brokerage) {
            return null;
        }

        $usage = $this->seats->usage($brokerage);
        $mentors = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::Mentor->value)->where('model_has_roles.organisation_id', $brokerage->id)->exists();
        $steps = [
            ['key' => 'verify', 'done' => $brokerage->trec_number !== null, 'href' => '/organisation/settings'],
            ['key' => 'agents', 'done' => $usage['used'] + $usage['pending'] > 0, 'href' => '/agents'],
            ['key' => 'seats', 'done' => is_array($brokerage->limits) && array_key_exists('payer', $brokerage->limits), 'href' => '/organisation/settings'],
            ['key' => 'manual', 'done' => PolicyManual::query()->where('organisation_id', $brokerage->id)->exists(), 'href' => '/organisation/virtual-broker'],
            ['key' => 'advertising', 'done' => $brokerage->advertising !== null && $brokerage->advertising !== [], 'href' => '/organisation/advertising'],
            ['key' => 'mentors', 'done' => $mentors, 'href' => '/organisation/mentors'],
        ];

        return ['trec_number' => $brokerage->trec_number, 'done' => count(array_filter($steps, static fn (array $s): bool => $s['done'])), 'total' => count($steps), 'steps' => $steps];
    }

    /** Only the brokerage's own brokers see the checklist. */
    public static function shows(User $user, ?Organisation $organisation, ?Role $role): bool
    {
        return $organisation !== null && $role === Role::Broker && $organisation->type === OrganisationType::Brokerage && $user->exists;
    }
}
