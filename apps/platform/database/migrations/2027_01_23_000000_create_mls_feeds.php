<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MLS feeds (B10.1, ADR 0042). REOS licenses each MLS's RESO feed as a
 * technology vendor, so one copy serves every agent on the platform: these
 * tables are platform-wide (no tenant). Agents link to their MLS member
 * record, matched by their TREC licence and confirmed by them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Replication state per MLS: where each RESO resource got to.
        Schema::create('mls_feeds', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64)->unique();
            // simulator or reso; switching purges the other's records.
            $table->string('source', 16)->nullable();
            $table->jsonb('cursors')->default('{}');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamps();
        });

        Schema::create('mls_offices', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64);
            $table->string('office_key', 128);
            $table->string('office_mls_id', 64)->nullable();
            $table->string('name', 200);
            $table->string('city', 120)->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'office_key']);
        });

        Schema::create('mls_members', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64);
            $table->string('member_key', 128);
            $table->string('member_mls_id', 64)->nullable();
            $table->string('name', 200);
            // MemberStateLicense, and its digits without leading zeros to match TREC numbers.
            $table->string('licence', 40)->nullable();
            $table->string('licence_digits', 40)->nullable()->index();
            $table->string('office_key', 128)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'member_key']);
        });

        Schema::create('mls_listings', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('integration', 64);
            $table->string('listing_key', 128);
            $table->string('listing_id', 64)->nullable();
            // coming_soon, active, under_contract, pending or closed.
            $table->string('status', 20);
            $table->string('property_type', 60)->nullable();
            $table->string('property_sub_type', 60)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('subdivision', 160)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Whole dollars, as the MLS publishes them.
            $table->unsignedBigInteger('list_price')->nullable();
            $table->unsignedBigInteger('original_list_price')->nullable();
            $table->unsignedBigInteger('close_price')->nullable();
            $table->date('close_date')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 4, 1)->nullable();
            $table->unsignedInteger('living_area')->nullable();
            $table->decimal('lot_acres', 10, 3)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('list_agent_key', 128)->nullable();
            $table->string('list_agent_name', 200)->nullable();
            $table->string('co_list_agent_key', 128)->nullable();
            $table->string('list_office_key', 128)->nullable();
            $table->string('list_office_name', 200)->nullable();
            $table->text('remarks')->nullable();
            $table->jsonb('photos')->default('[]');
            $table->string('virtual_tour', 500)->nullable();
            $table->date('listed_on')->nullable();
            // The MLS's display permissions (B10.2 applies them).
            $table->boolean('idx')->default(true);
            $table->boolean('address_shown')->default(true);
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'listing_key']);
            $table->index(['integration', 'list_agent_key']);
            $table->index(['integration', 'co_list_agent_key']);
            $table->index(['integration', 'status']);
        });

        Schema::create('mls_open_houses', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64);
            $table->string('open_house_key', 128);
            $table->string('listing_key', 128);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // public or broker.
            $table->string('kind', 10);
            $table->string('remarks', 500)->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'open_house_key']);
            $table->index(['integration', 'listing_key']);
        });

        // An agent's MLS member record: confirmed ("this is me") or declined ("not me").
        Schema::create('mls_member_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('integration', 64);
            $table->string('member_key', 128);
            $table->string('status', 10);
            $table->timestamps();
            $table->unique(['user_id', 'integration', 'member_key']);
            $table->index(['integration', 'member_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mls_member_links');
        Schema::dropIfExists('mls_open_houses');
        Schema::dropIfExists('mls_listings');
        Schema::dropIfExists('mls_members');
        Schema::dropIfExists('mls_offices');
        Schema::dropIfExists('mls_feeds');
    }
};
