<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first provider categories (A6.1, screen 68; prospect feedback), with
 * Texas flat monthly prices for the Basic, Pro and Premium plans. The super
 * admin adds and edits more on screen 68.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        ['loan-officer', 'Loan officer', 'NMLS', 'NMLS ID lookup', ['NMLS ID', 'Loan types', 'Languages'], null, 'live', [4900, 14900, 39900]],
        ['home-inspector', 'Home inspector', 'TREC inspector', 'TREC inspector license lookup', ['TREC license number', 'Inspection types', 'Service area'], null, 'live', [3900, 11900, 29900]],
        ['title-and-escrow', 'Title and escrow', 'TDI', 'Texas Department of Insurance license', ['TDI license number', 'Offices', 'Closing options'], null, 'live', [4900, 14900, 39900]],
        ['transaction-coordinator', 'Transaction coordinator', 'ID check + E&O', 'ID check + E&O certificate', ['Files per month', 'Tools used', 'Per-file fee'], 'Deal sharing with access switches', 'live', [2900, 8900, 19900]],
        ['photographer', 'Photographer', 'ID check', 'ID check', ['Services', 'Turnaround', 'Portfolio link'], null, 'live', [1900, 5900, 14900]],
        ['solar-installer', 'Solar installer', 'State license', 'Texas electrical contractor license', ['License number', 'Brands installed'], null, 'requested', []],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CATEGORIES as [$key, $name, $licence, $verification, $fields, $permissions, $status, $prices]) {
            if (DB::table('provider_categories')->where('key', $key)->exists()) {
                continue;
            }

            $id = DB::table('provider_categories')->insertGetId([
                'key' => $key, 'name' => $name, 'licence_check' => $licence, 'verification' => $verification,
                'profile_fields' => json_encode($fields, JSON_THROW_ON_ERROR), 'special_permissions' => $permissions,
                'areas_on_pro' => 3, 'status' => $status, 'requests' => $status === 'requested' ? 14 : 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach (array_combine(array_slice(['basic', 'pro', 'premium'], 0, count($prices)), $prices) ?: [] as $tier => $cents) {
                DB::table('provider_category_prices')->insert(['provider_category_id' => $id, 'state_code' => 'TX', 'tier' => $tier, 'monthly_cents' => $cents, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('provider_categories')->whereIn('key', array_column(self::CATEGORIES, 0))->delete();
    }
};
