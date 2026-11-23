<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course approvals per state (A7.1, ADR 0025): each a record of its own
 * (number, hours, delivery, approval and expiry dates, seat-time and
 * attendance rules), replacing the approval numbers kept on the course
 * (A5.3). Courses get an owner (a school, or REOS when empty) and a delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained('organisations')->restrictOnDelete();
            $table->string('delivery', 20)->default('self_paced')->after('type');
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('course_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('state_code', 2);
            $table->string('delivery', 20);
            $table->string('number', 60)->nullable();
            $table->decimal('hours', 5, 1)->nullable();
            $table->date('approved_on')->nullable();
            $table->date('expires_on')->nullable();
            // pending (filed, waiting for the regulator), approved, withdrawn.
            $table->string('status', 12)->default('pending');
            // Seat time and attendance (defaults from the state's rules, screen 62).
            $table->decimal('max_hours_per_day', 4, 1)->nullable();
            $table->unsignedSmallInteger('checkin_minutes')->nullable();
            $table->unsignedTinyInteger('presence_percent')->nullable();
            $table->unsignedTinyInteger('makeup_min_percent')->nullable();
            $table->unsignedSmallInteger('makeup_days')->nullable();
            $table->string('note', 500)->nullable();
            // Expiry reminders already sent (60, then 30 days before) and the expiry itself.
            $table->unsignedSmallInteger('reminded_days')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'state_code', 'delivery']);
            $table->index(['status', 'expires_on']);
        });

        // The approval numbers kept on courses (A5.3) become approved records.
        foreach (DB::table('courses')->whereNotNull('approvals')->get(['id', 'approvals', 'hours']) as $course) {
            $approvals = is_string($course->approvals) ? json_decode($course->approvals, true) : null;

            foreach (is_array($approvals) ? $approvals : [] as $state => $approval) {
                $number = is_array($approval) && is_string($approval['number'] ?? null) ? trim($approval['number']) : '';

                if ($number === '') {
                    continue;
                }

                DB::table('course_approvals')->insert([
                    'course_id' => $course->id,
                    'state_code' => strtoupper((string) $state),
                    'delivery' => 'self_paced',
                    'number' => $number,
                    'hours' => $course->hours,
                    'expires_on' => is_string($approval['expires_on'] ?? null) ? $approval['expires_on'] : null,
                    'status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('approvals');
        });

        // Texas seat time and attendance (screen 62), for databases seeded before it existed.
        $rule = collect(TexasRules::all())->firstWhere('key', 'courses.seat_time');
        $definition = RuleCatalogue::find('courses.seat_time');

        if (is_array($rule) && $definition !== null && ! DB::table('state_rules')->where('state_code', 'TX')->where('key', 'courses.seat_time')->exists()) {
            DB::table('state_rules')->insert([
                'state_code' => 'TX', 'topic' => $definition['topic']->value, 'key' => $rule['key'], 'type' => $definition['type']->value,
                'value' => json_encode($rule['value'], JSON_THROW_ON_ERROR), 'citation' => $rule['citation'], 'source_url' => $rule['source_url'],
                'effective_from' => $rule['effective_from'], 'checked_on' => '2026-11-23', 'version' => 1, 'status' => 'published',
                'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('state_rules')->where('key', 'courses.seat_time')->delete();

        Schema::table('courses', function (Blueprint $table) {
            $table->json('approvals')->nullable()->after('hours');
        });

        foreach (DB::table('course_approvals')->where('status', 'approved')->get() as $approval) {
            $course = DB::table('courses')->where('id', $approval->course_id)->first(['approvals']);
            $current = json_decode((string) ($course->approvals ?? 'null'), true);
            $current = is_array($current) ? $current : [];
            $current[$approval->state_code] = ['number' => $approval->number, 'expires_on' => $approval->expires_on];
            DB::table('courses')->where('id', $approval->course_id)->update(['approvals' => json_encode($current)]);
        }

        Schema::drop('course_approvals');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'status']);
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn('delivery');
        });
    }
};
