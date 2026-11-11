<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    /**
     * Kategori bawaan HelpDesk (idempoten — aman dijalankan berulang).
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Technical Support', 'description' => 'Bantuan teknis umum terkait layanan dan sistem.'],
            ['name' => 'Hardware', 'description' => 'Kerusakan atau kendala perangkat keras.'],
            ['name' => 'Software', 'description' => 'Kendala pada aplikasi atau perangkat lunak.'],
            ['name' => 'Network', 'description' => 'Kendala jaringan, koneksi, dan internet.'],
            ['name' => 'Account Access', 'description' => 'Masalah login, akun, dan hak akses pengguna.'],
            ['name' => 'General Request', 'description' => 'Permintaan umum di luar kategori teknis.'],
        ];

        foreach ($categories as $category) {
            TicketCategory::firstOrCreate(
                ['name' => $category['name']],
                [
                    'slug' => TicketCategory::makeSlug($category['name']),
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
