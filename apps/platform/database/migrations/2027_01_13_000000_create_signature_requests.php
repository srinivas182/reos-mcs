<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signatures on deals (B9.3, ADR 0038): the files kept on a deal (uploads,
 * and the signed copy and certificate filed when an envelope completes),
 * and each envelope sent from a deal through the agent's own DocuSign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            // The agent's DocuSign connection; kept (as history) if they disconnect.
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('envelope_id', 64)->nullable();
            $table->string('subject', 100);
            // draft, sent, delivered, completed, declined, voided.
            $table->string('status', 20)->default('draft');
            // Each signer: name, email, routing order, status and when.
            $table->json('signers');
            // The documents' names, in order.
            $table->json('documents');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('filed_at')->nullable();
            // Where the signed copy went beyond REOS: the tool's id, or null.
            $table->string('filed_in', 40)->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'envelope_id']);
            $table->index(['deal_id', 'status']);
        });

        Schema::create('deal_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('signature_request_id')->nullable()->constrained()->nullOnDelete();
            // upload, signed or certificate.
            $table->string('kind', 20);
            $table->string('name', 200);
            $table->string('path', 300);
            $table->unsignedInteger('size');
            // Virus scan (ClamAV, as for lesson files): pending, clean or infected. Only clean files open or are sent.
            $table->string('scan_status', 10)->default('pending');
            $table->timestamps();
            $table->index(['deal_id', 'kind']);
        });

        RowLevelSecurity::protect('signature_requests', allowPersonal: true);
        RowLevelSecurity::protect('deal_files', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('deal_files');
        RowLevelSecurity::release('signature_requests');
        Schema::dropIfExists('deal_files');
        Schema::dropIfExists('signature_requests');
    }
};
