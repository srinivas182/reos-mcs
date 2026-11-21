<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\User;
use App\Services\Authorization\RoleCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A3.2: role assignments belong to an organisation, each device remembers
 * the last role used, and the Sprint 2 `is_super_admin` flag becomes the
 * super-admin role in the REOS organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            });
        }

        Schema::table('known_devices', function (Blueprint $table): void {
            $table->string('active_role', 40)->nullable();
            $table->foreignId('active_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->timestamp('role_chosen_at')->nullable();
        });

        app(RoleCatalogue::class)->sync();

        // The table directly, not the model: later migrations add columns the model uses.
        $platform = (object) ['id' => DB::table('organisations')->where('type', 'platform')->whereNull('parent_id')->value('id')
            ?? DB::table('organisations')->insertGetId(['type' => 'platform', 'name' => 'REOS platform', 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()])];
        $role = DB::table('roles')->where('name', Role::SuperAdmin->value)->value('id');

        DB::table('users')->where('is_super_admin', true)->pluck('id')->each(function (mixed $id) use ($platform, $role): void {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $role,
                'model_type' => (new User)->getMorphClass(),
                'model_id' => $id,
                'organisation_id' => $platform->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('locale');
        });

        $role = DB::table('roles')->where('name', Role::SuperAdmin->value)->value('id');
        DB::table('users')
            ->whereIn('id', DB::table('model_has_roles')->where('role_id', $role)->select('model_id'))
            ->update(['is_super_admin' => true]);

        Schema::table('known_devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_organisation_id');
            $table->dropColumn(['active_role', 'role_chosen_at']);
        });

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropForeign(['organisation_id']);
            });
        }
    }
};
