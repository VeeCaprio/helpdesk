<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Konsolidasi role: super_admin dipindahkan ke admin, lalu dihapus.
     * Data user, tiket, dan permission tidak dihapus.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = config('auth.defaults.guard', 'web');
        $superAdminId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', $guard)
            ->value('id');

        if (! $superAdminId) {
            return;
        }

        $adminId = DB::table('roles')
            ->where('name', 'admin')
            ->where('guard_name', $guard)
            ->value('id');

        if (! $adminId) {
            $adminId = DB::table('roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $assignments = DB::table('model_has_roles')
            ->where('role_id', $superAdminId)
            ->get();

        foreach ($assignments as $assignment) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $adminId,
                'model_type' => $assignment->model_type,
                'model_id' => $assignment->model_id,
            ]);
        }

        DB::table('model_has_roles')->where('role_id', $superAdminId)->delete();
        DB::table('role_has_permissions')->where('role_id', $superAdminId)->delete();
        DB::table('roles')->where('id', $superAdminId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan: super_admin bukan lagi role bisnis.
    }
};
