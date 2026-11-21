<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisations, pulled forward from A4.1 so a role can belong to this
 * brokerage or school from the start: type, name and parent only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30)->index();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
