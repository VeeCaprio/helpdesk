<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission yang dipakai resource Filament HelpDesk.
     * Dibuat di sini agar seeding tetap deterministik tanpa bergantung
     * pada `shield:generate` (yang tetap dijalankan saat project:init).
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'view_any_ticket',
        'view_ticket',
        'create_ticket',
        'update_ticket',
        'delete_ticket',
        'delete_any_ticket',
        'view_any_ticket::category',
        'view_ticket::category',
        'create_ticket::category',
        'update_ticket::category',
        'delete_ticket::category',
        'delete_any_ticket::category',
        'view_any_user',
        'view_user',
        'create_user',
        'update_user',
        'delete_user',
        'delete_any_user',
        'view_any_role',
        'view_role',
        'create_role',
        'update_role',
        'delete_role',
        'delete_any_role',
        'view_any_activity',
        'view_activity',
        'create_activity',
        'update_activity',
        'delete_activity',
        'delete_any_activity',
        'widget_LatestAccessLogs',
        'widget_OverlookWidget',
    ];

    /**
     * Permission minimum per role. Admin mendapatkan seluruh permission.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        User::ROLE_STAFF => [
            'view_any_ticket',
            'view_ticket',
            'create_ticket',
            'update_ticket',
        ],
        User::ROLE_USER => [
            'view_any_ticket',
            'view_ticket',
            'create_ticket',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [];

        foreach (User::ROLES as $role) {
            $roles[$role] = Role::findOrCreate($role, 'web');
        }

        // Admin: seluruh permission yang ada, termasuk permission baru
        // yang mungkin dihasilkan Shield untuk widget/halaman baru.
        $roles[User::ROLE_ADMIN]->syncPermissions(Permission::query()->get());

        // Staff & user: permission minimum; sinkronisasi juga mencabut
        // permission lama yang tidak lagi relevan.
        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            $roles[$role]->syncPermissions(
                Permission::query()->whereIn('name', $permissions)->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
