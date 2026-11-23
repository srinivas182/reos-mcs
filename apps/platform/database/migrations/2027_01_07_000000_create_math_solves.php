<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Math lab (A9.2c): each step checked, and each problem solved, per candidate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('math_solves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('seed');
            $table->json('steps');
            $table->timestamp('solved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'kind', 'seed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('math_solves');
    }
};
