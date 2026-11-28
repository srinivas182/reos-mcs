<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Categories drive provider onboarding (B15.1, ADR 0073):
 *
 * - each category's licence step (`licence_kind`: NMLS, TREC inspector,
 *   TDI, or none for business details and an ID);
 * - profile fields with a type (text, list or yes/no), from the plain
 *   labels saved before;
 * - who asked for a new category, so the admin's queue counts real people;
 * - the provider's choice of category and their answers, before they buy.
 */
return new class extends Migration
{
    private const KINDS = ['NMLS' => 'nmls', 'TREC inspector' => 'trec_inspector', 'TDI' => 'tdi'];

    public function up(): void
    {
        Schema::table('provider_categories', function (Blueprint $table): void {
            $table->string('licence_kind', 20)->nullable();
        });

        foreach (DB::table('provider_categories')->get(['id', 'licence_check', 'profile_fields']) as $row) {
            $labels = is_string($row->profile_fields) ? json_decode($row->profile_fields, true) : [];
            $fields = [];

            foreach (is_array($labels) ? $labels : [] as $label) {
                if (is_string($label) && $label !== '') {
                    $fields[] = ['key' => Str::slug($label, '_'), 'label' => $label, 'type' => 'text', 'options' => []];
                }
            }

            DB::table('provider_categories')->where('id', $row->id)->update([
                'licence_kind' => self::KINDS[(string) $row->licence_check] ?? null,
                'profile_fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::create('provider_category_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->unique(['provider_category_id', 'user_id']);
        });

        Schema::create('provider_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_category_id')->nullable()->constrained()->nullOnDelete();
            // Answers to the category's profile fields, by field key.
            $table->json('fields')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('provider_category_requests');

        foreach (DB::table('provider_categories')->get(['id', 'profile_fields']) as $row) {
            $fields = is_string($row->profile_fields) ? json_decode($row->profile_fields, true) : [];
            $labels = array_values(array_filter(array_map(static fn (mixed $f): ?string => is_array($f) && is_string($f['label'] ?? null) ? $f['label'] : null, is_array($fields) ? $fields : [])));
            DB::table('provider_categories')->where('id', $row->id)->update(['profile_fields' => json_encode($labels, JSON_THROW_ON_ERROR)]);
        }

        Schema::table('provider_categories', function (Blueprint $table): void {
            $table->dropColumn('licence_kind');
        });
    }
};
