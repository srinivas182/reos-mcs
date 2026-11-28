<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Models\ProviderCategory;
use App\Models\ProviderCategoryPrice;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Categories drive provider onboarding (B15.1, ADR 0073): the category grid,
 * the licence step and the profile fields all come from the super admin's
 * settings on screen 68, so a new category needs no code.
 *
 * - **Grid:** live categories, with the flat monthly prices in the
 *   provider's state and the licence the category asks for.
 * - **Request a category:** a provider whose work isn't listed asks for it;
 *   the category appears in the admin's queue as "requested", counted once
 *   per person.
 * - **Profile fields:** text, a list (one or more values, from the
 *   admin's options when given) or yes/no, checked by type.
 *
 * @phpstan-type Field array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}
 * @phpstan-type CategoryCard array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<Field>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}
 * @phpstan-type Joining array{category: CategoryCard|null, fields: array<string, string|bool|list<string>>, missing: list<string>}
 */
final readonly class ProviderJoining
{
    public function __construct(private AuditLogger $audit) {}

    /** @return list<CategoryCard> */
    public function categories(string $state): array
    {
        $cards = [];

        foreach (ProviderCategory::query()->where('status', 'live')->with(['prices' => static fn ($q) => $q->where('state_code', strtoupper($state))])->orderBy('name')->get() as $category) {
            $cards[] = self::card($category);
        }

        return $cards;
    }

    /** @return CategoryCard */
    public static function card(ProviderCategory $category): array
    {
        $price = static fn (string $tier): ?int => $category->prices->first(static fn (ProviderCategoryPrice $p): bool => $p->tier === $tier)?->monthly_cents;
        $kind = match ($category->licence_kind) {
            'nmls' => 'nmls',
            'trec_inspector' => 'trec_inspector',
            'tdi' => 'tdi',
            default => 'business',
        };

        return [
            'key' => $category->key, 'name' => $category->name, 'description' => $category->description,
            'licence' => ['kind' => $kind, 'label' => $category->verification ?? $category->licence_check],
            'fields' => self::fieldsOf($category), 'areas_on_pro' => $category->areas_on_pro,
            'prices' => ['basic' => $price('basic'), 'pro' => $price('pro'), 'premium' => $price('premium')],
        ];
    }

    /**
     * A provider asks for a category that isn't listed. It goes to the admin as "requested".
     *
     * @return array{key: string, name: string, status: string, requests: int}
     */
    public function request(User $user, string $name, ?string $note): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $key = Str::slug($name);
        $existing = ProviderCategory::query()->where('key', $key)->orWhereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($existing?->status === 'live') {
            throw ValidationException::withMessages(['name' => __('providers.join.errors.exists', ['name' => $existing->name])]);
        }

        $category = DB::transaction(function () use ($existing, $name, $key, $user, $note): ProviderCategory {
            $category = $existing ?? ProviderCategory::query()->create(['key' => $key ?: 'category-'.Str::lower(Str::random(6)), 'name' => $name, 'status' => 'requested', 'requests' => 0, 'profile_fields' => []]);
            $asked = DB::table('provider_category_requests')->insertOrIgnore([
                'provider_category_id' => $category->id, 'user_id' => $user->id, 'note' => $note === null ? null : mb_substr(trim($note), 0, 300), 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($asked > 0) {
                $category->increment('requests');
            }

            return $category->refresh();
        });

        $this->audit->record('provider_category.requested', 'provider_category', $category->key, ['name' => $category->name], $user);

        return ['key' => $category->key, 'name' => $category->name, 'status' => $category->status, 'requests' => $category->requests];
    }

    /** @return Joining */
    public function profile(User $user): array
    {
        $profile = ProviderProfile::query()->with('category.prices')->where('user_id', $user->id)->first();
        $category = $profile?->category;

        if ($category === null || $category->status !== 'live') {
            return ['category' => null, 'fields' => [], 'missing' => []];
        }

        $answers = $profile->fields ?? [];

        return [
            'category' => self::card($category),
            'fields' => $answers,
            'missing' => array_values(array_map(static fn (array $f): string => $f['key'], array_filter(self::fieldsOf($category), static fn (array $f): bool => ! array_key_exists($f['key'], $answers)))),
        ];
    }

    /** @return Joining */
    public function choose(User $user, string $key): array
    {
        $category = ProviderCategory::query()->where('key', $key)->where('status', 'live')->first();

        if ($category === null) {
            throw ValidationException::withMessages(['category' => __('providers.join.errors.category')]);
        }

        $profile = ProviderProfile::query()->firstOrNew(['user_id' => $user->id]);
        $keys = array_column(self::fieldsOf($category), 'key');
        $kept = $profile->provider_category_id === $category->id ? array_intersect_key($profile->fields ?? [], array_flip($keys)) : [];
        $profile->fill(['provider_category_id' => $category->id, 'fields' => $kept])->save();

        return $this->profile($user);
    }

    /**
     * Saves the provider's answers, each checked against its field's type.
     *
     * @param  array<array-key, mixed>  $answers
     * @return Joining
     */
    public function answer(User $user, array $answers): array
    {
        $profile = ProviderProfile::query()->with('category')->where('user_id', $user->id)->first();
        $category = $profile?->category;

        if ($profile === null || $category === null) {
            throw ValidationException::withMessages(['category' => __('providers.join.errors.chooseFirst')]);
        }

        $clean = $profile->fields ?? [];
        $errors = [];

        foreach (self::fieldsOf($category) as $field) {
            if (! array_key_exists($field['key'], $answers)) {
                continue;
            }

            $value = $answers[$field['key']];
            $checked = self::checked($field, $value);

            if ($checked === null) {
                $errors["fields.{$field['key']}"] = __('providers.join.errors.'.$field['type'], ['field' => $field['label']]);

                continue;
            }

            $clean[$field['key']] = $checked;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $profile->forceFill(['fields' => $clean])->save();

        return $this->profile($user);
    }

    /**
     * @param  Field  $field
     * @return string|bool|list<string>|null null when it doesn't fit the type
     */
    private static function checked(array $field, mixed $value): string|bool|array|null
    {
        return match ($field['type']) {
            'yes_no' => is_bool($value) ? $value : null,
            'list' => self::list($field, $value),
            'text' => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 500 ? trim($value) : null,
        };
    }

    /**
     * @param  Field  $field
     * @return list<string>|null
     */
    private static function list(array $field, mixed $value): ?array
    {
        if (! is_array($value) || $value === [] || count($value) > 20) {
            return null;
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '' || mb_strlen($item) > 80) {
                return null;
            }

            if ($field['options'] !== [] && ! in_array(trim($item), $field['options'], true)) {
                return null;
            }

            $items[] = trim($item);
        }

        return array_values(array_unique($items));
    }

    /** @return list<Field> */
    private static function fieldsOf(ProviderCategory $category): array
    {
        $fields = [];

        foreach ($category->profile_fields ?? [] as $field) {
            $fields[] = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'options' => $field['options']];
        }

        return $fields;
    }
}
