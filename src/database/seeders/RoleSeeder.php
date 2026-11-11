<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Tiga role bisnis HelpDesk:
     * - admin : akses penuh (tiket, kategori, user, laporan, pengaturan)
     * - staff : menangani tiket yang ditugaskan dan membuat tiket
     * - user  : pelapor; membuat dan melihat tiket miliknya
     */
    public function run(): void
    {
        foreach (User::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
