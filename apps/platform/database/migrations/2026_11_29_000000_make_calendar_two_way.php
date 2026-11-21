<?php

declare(strict_types=1);

use App\Tenancy\Tenancy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way calendar (B4.1, ADR 0017).
 *
 * - The register entry `google-workspace` becomes `google-calendar` (calendar
 *   only, Sprint 4 decision 1); rows that name it follow.
 * - `calendar_events` becomes the REOS calendar: every event belongs to a
 *   person, may come from a tool or be made in REOS, and remembers what still
 *   has to be written back.
 * - `calendar_conflicts` keeps the losing version of an event changed in two
 *   places, for 30 days, so it can be undone.
 * - `simulator_records` is the Simulator's stand-in for the tool's own data,
 *   so demo connections can be written to and changed "in the tool".
 */
return new class extends Migration
{
    private const OLD_ID = 'google-workspace';

    private const NEW_ID = 'google-calendar';

    public function up(): void
    {
        $this->renameIntegration(self::OLD_ID, self::NEW_ID);

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Made in REOS (`reos`) or the register id of the tool it came from.
            $table->string('source', 64)->default('reos')->after('connection_id');
            // appointment, showing, deadline, session, ce or event (from a tool).
            $table->string('kind', 20)->default('event')->after('source');
            $table->text('description')->nullable()->after('location');
            // Guest emails.
            $table->json('attendees')->nullable()->after('description');
            // The contact or deal it is about (CRM, Sprint 5 onward).
            $table->string('related_type', 40)->nullable()->after('attendees');
            $table->string('related_id', 64)->nullable()->after('related_type');
            // From a calendar shared as "busy only": no title, place or guests are kept.
            $table->boolean('busy')->default(false)->after('all_day');
            $table->boolean('notify_guests')->default(false)->after('busy');
            $table->string('etag', 255)->nullable()->after('html_link');
            // When it last changed in REOS, for "newest change wins".
            $table->timestamp('local_updated_at')->nullable()->after('remote_updated_at');
            // pending (a change still to write to the tool), failed, or null (in step).
            $table->string('push_state', 16)->nullable()->after('local_updated_at');
            $table->string('push_error', 120)->nullable()->after('push_state');
            $table->softDeletes();
        });

        DB::table('calendar_events')->update(['source' => self::NEW_ID]);
        $this->asPlatform(function (): void {
            DB::table('connections')->where('owner_type', 'user')->where('integration_id', self::NEW_ID)->orderBy('id')
                ->each(function (object $connection): void {
                    DB::table('calendar_events')->where('connection_id', $connection->id)->update(['user_id' => $connection->owner_id]);
                });
        });
        DB::table('calendar_events')->whereNull('user_id')->delete();

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropUnique(['connection_id', 'external_id']);
            $table->dropForeign(['connection_id']);
        });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            // REOS's own events stay when a tool is disconnected.
            $table->foreignId('connection_id')->nullable()->change();
            $table->foreign('connection_id')->references('id')->on('connections')->nullOnDelete();
            $table->string('external_id')->nullable()->change();
            $table->string('calendar_id')->nullable()->change();
            $table->unique(['connection_id', 'calendar_id', 'external_id']);
            $table->index(['user_id', 'starts_at']);
            $table->index('push_state');
        });

        Schema::create('calendar_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null once the event is gone (deleted in the tool); Undo makes it again.
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            // Which side's version was kept: `reos` or `tool`.
            $table->string('kept', 8);
            // edited_both, deleted_in_tool or deleted_in_reos.
            $table->string('reason', 20);
            // The losing version: title, times, place, notes, guests (and `deleted`).
            $table->json('losing');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['calendar_event_id', 'created_at']);
        });

        Schema::create('simulator_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('stream', 40);
            $table->string('container', 255);
            $table->string('external_id', 255);
            $table->json('data');
            $table->string('etag', 64);
            $table->boolean('deleted')->default(false);
            $table->timestamps();

            $table->unique(['connection_id', 'stream', 'container', 'external_id']);
            $table->index(['connection_id', 'stream', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_records');
        Schema::dropIfExists('calendar_conflicts');

        // Only tool events existed before: REOS's own events can't go back.
        DB::table('calendar_events')->whereNull('connection_id')->orWhereNull('external_id')->orWhereNotNull('deleted_at')->delete();

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropUnique(['connection_id', 'calendar_id', 'external_id']);
            $table->dropIndex(['user_id', 'starts_at']);
            $table->dropIndex(['push_state']);
            $table->dropForeign(['connection_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignId('connection_id')->nullable(false)->change();
            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
            $table->string('external_id')->nullable(false)->change();
            $table->string('calendar_id')->nullable(false)->change();
            $table->unique(['connection_id', 'external_id']);
            $table->dropColumn([
                'user_id', 'source', 'kind', 'description', 'attendees', 'related_type', 'related_id', 'busy',
                'notify_guests', 'etag', 'local_updated_at', 'push_state', 'push_error', 'deleted_at',
            ]);
        });

        $this->renameIntegration(self::NEW_ID, self::OLD_ID);
    }

    /** Every table that names a tool by its register id. */
    private function renameIntegration(string $from, string $to): void
    {
        $this->asPlatform(function () use ($from, $to): void {
            foreach (['integration_settings', 'integration_credentials', 'integration_checks', 'connections', 'oauth_authorizations', 'webhook_receipts', 'catalogue_settings', 'organisation_hidden_integrations', 'integration_interests', 'tool_requests'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'integration_id')) {
                    DB::table($table)->where('integration_id', $from)->update(['integration_id' => $to]);
                }
            }
        });
    }

    /**
     * Row-level security hides tenant rows from migrations too, unless they run as platform.
     *
     * @param  Closure(): void  $work
     */
    private function asPlatform(Closure $work): void
    {
        app(Tenancy::class)->asPlatform($work);
    }
};
