<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Features\FlagCatalogue;
use App\Features\RolloutStage;
use App\Models\FeatureFlag;
use App\Models\Organisation;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

/**
 * Features and releases as on screen 70 (A6.2): Messages tried by Lone Star
 * Realty, Video rooms with REOS staff, a release with notes per role, and a
 * week of uses for the error column.
 */
final readonly class DemoReleases
{
    public function seed(): void
    {
        foreach (FlagCatalogue::FLAGS as $key => $flag) {
            FeatureFlag::query()->firstOrCreate(['key' => $key], [
                'name' => $flag['name'], 'description' => $flag['description'], 'owner' => $flag['owner'],
                'tenant_feature' => $flag['tenant'], 'stage' => $flag['stage'], 'stage_changed_at' => now()->subDays(6),
            ]);
        }

        $loneStar = Organisation::query()->where('name', 'Lone Star Realty')->value('id');
        FeatureFlag::query()->where('key', 'messages')->update([
            'stage' => RolloutStage::Tenants->value,
            'tenant_ids' => json_encode(is_int($loneStar) ? [$loneStar] : [], JSON_THROW_ON_ERROR),
            'roles' => json_encode(['agent', 'broker', 'mentor'], JSON_THROW_ON_ERROR),
            'stage_changed_at' => now()->subDays(3),
        ]);

        $flags = FeatureFlag::query()->whereIn('key', ['messages', 'spanish_ui', 'virtual_broker'])->pluck('id', 'key');

        foreach ($flags as $key => $id) {
            for ($hour = 1; $hour <= 48; $hour++) {
                DB::table('feature_flag_metrics')->updateOrInsert(
                    ['feature_flag_id' => $id, 'minute' => now()->subHours($hour)->startOfMinute()->toDateTimeString()],
                    ['uses' => $key === 'messages' ? 9 : 40, 'errors' => $hour % 12 === 0 ? 1 : 0],
                );
            }
        }

        Release::query()->firstOrCreate(['version' => '6.1.0'], [
            'released_on' => now()->subDays(2)->toDateString(),
            'published_at' => now()->subDays(2),
            'notes' => [
                'agent' => 'Brokerages and schools can now sign up and pay on reos.com, and your calendar shows on Today.',
                'broker' => 'You can start a new brokerage on reos.com and pay on the web; its agents join with your link.',
                'school_admin' => 'Your school can sign up and pay on reos.com. Sales and payouts show what each course earns.',
                'super_admin' => 'Platform overview, provider categories and AI and credits are live in the back office.',
            ],
            'flags' => null,
        ]);
    }
}
