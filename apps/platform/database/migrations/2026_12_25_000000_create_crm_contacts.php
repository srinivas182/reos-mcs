<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CRM data model (B7.1, ADR 0027): one contact per person in an agent's
 * (or brokerage's) world, linked to the same person in each source (a CRM,
 * the phone's contacts), with notes, tasks, tags and an activity log that
 * also records sync conflicts in plain English.
 */
return new class extends Migration
{
    public function up(): void
    {
        $owned = function (Blueprint $table): void {
            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');
            $expression = "case when owner_type = 'organisation' then owner_id end";
            $column = $table->unsignedBigInteger('organisation_id')->nullable();
            // PostgreSQL only has stored generated columns; SQLite only virtual ones.
            DB::getDriverName() === 'pgsql' ? $column->storedAs($expression) : $column->virtualAs($expression);
        };

        Schema::create('contacts', function (Blueprint $table) use ($owned) {
            $table->id();
            $table->uuid('uuid')->unique();
            $owned($table);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            // [{value, source}] (emails lower-cased; phones in E.164).
            $table->json('emails');
            $table->json('phones');
            $table->json('address')->nullable();
            $table->date('birthday')->nullable();
            $table->string('stage', 60)->nullable();
            // When each field last changed, for "newest change wins" field by field.
            $table->json('field_times');
            $table->foreignId('merged_into_id')->nullable()->constrained('contacts')->nullOnDelete();
            // What a merge moved, so the merge can be undone (split).
            $table->json('merge_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'deleted_at']);
        });

        Schema::create('contact_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // "user:12" or "organisation:3": a source's ids are unique per owner.
            $table->string('owner_key', 40);
            // "phone", or the integration id (follow-up-boss, kvcore).
            $table->string('source', 40);
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 191);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            // A fingerprint of what REOS last wrote there, so its own write coming back isn't a change.
            $table->string('echo_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['owner_key', 'source', 'external_id']);
            $table->index(['connection_id']);
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('source', 40);
            $table->string('external_id', 191)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // The REOS to-do: done in one is done in the other.
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40);
            $table->string('external_id', 191)->nullable();
            $table->timestamps();

            $table->unique(['todo_id']);
        });

        Schema::create('contact_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // created, linked, changed, kept, removed, merged, split, call, email, showing, stage.
            $table->string('kind', 20);
            $table->string('source', 40);
            $table->json('details')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contact_id', 'occurred_at']);
        });

        Schema::create('tags', function (Blueprint $table) use ($owned) {
            $table->id();
            $owned($table);
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'name']);
        });

        Schema::create('contact_tag', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'tag_id']);
        });

        Schema::create('contact_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('other_id')->constrained('contacts')->cascadeOnDelete();
            // Only "name" today: a name alone suggests a merge, never makes one.
            $table->string('reason', 20);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'other_id']);
        });

        RowLevelSecurity::protect('contacts', allowPersonal: true);
        RowLevelSecurity::protect('tags', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('tags');
        RowLevelSecurity::release('contacts');
        Schema::dropIfExists('contact_suggestions');
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contact_activities');
        Schema::dropIfExists('contact_tasks');
        Schema::dropIfExists('contact_notes');
        Schema::dropIfExists('contact_links');
        Schema::dropIfExists('contacts');
    }
};
