<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agent's public page (B10.2b): their MLS listings with each MLS's
 * display rules applied, at reos.com/agent/{handle}. Off until the agent
 * publishes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('handle', 80)->unique();
            $table->boolean('published')->default(false);
            $table->string('bio', 600)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_pages');
    }
};
