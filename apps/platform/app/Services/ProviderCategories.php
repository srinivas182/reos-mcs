<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProviderCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provider categories (screen 68, A6.1): unlimited, each with its own
 * licence check, profile fields, permissions and flat monthly prices per
 * state and tier. Every change is audited with before and after.
 */
final readonly class ProviderCategories
{
    private const AUDITED = ['name', 'description', 'licence_check', 'verification', 'profile_fields', 'special_permissions', 'areas_on_pro', 'status'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, int>>  $prices  state => tier => cents
     */
    public function save(?ProviderCategory $category, array $data, array $prices, User $actor): ProviderCategory
    {
        return DB::transaction(function () use ($category, $data, $prices, $actor): ProviderCategory {
            $creating = $category === null;
            $category ??= new ProviderCategory(['key' => $this->key(is_string($data['name'] ?? null) ? $data['name'] : 'category')]);
            $before = $creating ? null : $this->snapshot($category);

            $category->fill([...array_intersect_key($data, array_flip(self::AUDITED)), 'updated_by' => $actor->id])->save();

            foreach ($prices as $state => $tiers) {
                foreach ($tiers as $tier => $cents) {
                    $category->prices()->updateOrCreate(['state_code' => strtoupper($state), 'tier' => $tier], ['monthly_cents' => $cents]);
                }
            }

            $after = $this->snapshot($category->refresh());

            if ($creating) {
                $this->audit->record('provider_category.created', 'provider_category', $category->key, ['name' => $category->name], $actor);
            } elseif ($before !== $after) {
                $this->audit->record('provider_category.updated', 'provider_category', $category->key, ['before' => $before, 'after' => $after], $actor);
            }

            return $category;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(ProviderCategory $category): array
    {
        $prices = [];

        foreach ($category->prices()->orderBy('state_code')->orderBy('tier')->get() as $price) {
            $prices[$price->state_code][$price->tier] = $price->monthly_cents;
        }

        return [...array_intersect_key($category->attributesToArray(), array_flip(self::AUDITED)), 'prices' => $prices];
    }

    private function key(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $key = $base;

        for ($n = 2; ProviderCategory::query()->where('key', $key)->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }
}
