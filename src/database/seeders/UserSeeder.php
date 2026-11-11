<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Akun admin dibuat dari env di production. Akun demo (staff & reporter)
     * hanya dibuat di lingkungan non-production.
     */
    public function run(): void
    {
        $isProduction = app()->environment('production');
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if ($isProduction && (blank($adminEmail) || blank($adminPassword))) {
            $this->command?->warn(
                'ADMIN_EMAIL/ADMIN_PASSWORD belum diisi. Seeder akun dilewati; buat admin secara manual.'
            );

            return;
        }

        $adminEmail = $adminEmail ?: 'admin@admin.com';
        $adminPassword = $adminPassword ?: 'password';

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => Hash::make($adminPassword),
            ]
        );

        if ($admin->name === 'Super Admin') {
            $admin->update(['name' => 'Administrator']);
        }

        $admin->syncRoles([User::ROLE_ADMIN]);

        if ($isProduction) {
            return;
        }

        $demoPassword = env('DEMO_PASSWORD', 'password');

        $demoUsers = [
            ['email' => 'staff@admin.com', 'name' => 'Budi Santoso', 'role' => User::ROLE_STAFF],
            ['email' => 'staff2@admin.com', 'name' => 'Siti Rahma', 'role' => User::ROLE_STAFF],
            ['email' => 'user@admin.com', 'name' => 'Andi Wijaya', 'role' => User::ROLE_USER],
            ['email' => 'user2@admin.com', 'name' => 'Dewi Lestari', 'role' => User::ROLE_USER],
        ];

        foreach ($demoUsers as $demoUser) {
            $user = User::firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => Hash::make($demoPassword),
                ]
            );

            // Rapikan nama akun demo lama tanpa mengubah password.
            if ($user->name !== $demoUser['name']) {
                $user->update(['name' => $demoUser['name']]);
            }

            $user->syncRoles([$demoUser['role']]);
        }
    }
}
