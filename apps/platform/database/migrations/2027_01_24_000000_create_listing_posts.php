<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing posts (B10.2, ADR 0042): an agent's post about one of their MLS
 * listings, with photos within the MLS's licence terms and the attribution
 * it requires. Social channels publish them in B11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('integration', 64);
            $table->string('listing_key', 128);
            $table->string('caption', 2200);
            // The chosen photos' URLs, in order (never more than the MLS allows).
            $table->jsonb('photos')->default('[]');
            // Added to every post as the MLS requires; the agent can't edit it.
            $table->string('attribution', 300);
            $table->timestamps();
            $table->unique(['user_id', 'integration', 'listing_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_posts');
    }
};
