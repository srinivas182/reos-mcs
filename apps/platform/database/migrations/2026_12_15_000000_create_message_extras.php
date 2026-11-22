<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages, part two (B6.1b, ADR 0023): attachments (scanned like lesson
 * files), blocks, reports (with a copy of the reported messages) and
 * announcements with read receipts. Reports and announcements belong to a
 * tenant and keep row-level security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name', 200);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->uuid('uuid')->unique();
            // pending → clean or infected (scanned like lesson files).
            $table->string('scan_status', 10)->default('pending');
            $table->timestamp('created_at');
        });

        Schema::create('message_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->unique(['blocker_id', 'blocked_id']);
        });

        Schema::create('message_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 20);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('details', 1000)->nullable();
            // A copy of the reported messages, so a later edit or delete can't hide them.
            $table->json('messages');
            $table->string('status', 12)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('body');
            // Who it went to: roles, teams, states (empty: every member).
            $table->json('audience');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        Schema::create('announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        RowLevelSecurity::protect('message_reports');
        RowLevelSecurity::protect('announcements');
        RowLevelSecurity::protect('announcement_recipients');
    }

    public function down(): void
    {
        RowLevelSecurity::release('announcement_recipients');
        RowLevelSecurity::release('announcements');
        RowLevelSecurity::release('message_reports');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('message_blocks');
        Schema::dropIfExists('message_attachments');
    }
};
