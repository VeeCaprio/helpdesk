<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoTicketSeeder extends Seeder
{
    /**
     * Data demo realistis untuk dashboard, laporan, dan uji coba UI.
     * Hanya berjalan di non-production dan hanya bila tabel tiket masih kosong.
     */
    public function run(): void
    {
        if (app()->environment('production') || Ticket::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@admin.com')->first();
        $staff = User::query()
            ->whereIn('email', ['staff@admin.com', 'staff2@admin.com'])
            ->orderBy('email')
            ->get()
            ->values();
        $reporters = User::query()
            ->whereIn('email', ['user@admin.com', 'user2@admin.com'])
            ->orderBy('email')
            ->get()
            ->values();
        $categories = TicketCategory::query()->pluck('id', 'slug');

        if (! $admin || $staff->count() < 2 || $reporters->count() < 2 || $categories->isEmpty()) {
            return;
        }

        Auth::login($admin);

        try {
            foreach ($this->samples() as $index => $sample) {
                $createdAt = now()
                    ->subDays($sample['days_ago'])
                    ->setTime(8 + ($index % 9), ($index * 7) % 60);

                $assignee = $sample['assignee'] !== null
                    ? $staff[$sample['assignee']]
                    : null;

                $isFinished = in_array($sample['status'], ['resolved', 'closed'], true);

                $ticket = Ticket::create([
                    'subject' => $sample['subject'],
                    'description' => $sample['description'],
                    'category_id' => $categories[$sample['category']],
                    'priority' => $sample['priority'],
                    'status' => $sample['status'],
                    'reporter_id' => $reporters[$sample['reporter']]->id,
                    'assigned_to' => $assignee?->id,
                    'resolved_at' => $isFinished
                        ? $createdAt->copy()->addHours(4 + ($index % 12))
                        : null,
                ]);

                // Pertahankan tanggal demo tanpa memicu observer lagi.
                $ticket->timestamps = false;
                $ticket->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->saveQuietly();
                $ticket->timestamps = true;

                $ticket->histories()->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($sample['comments'] ?? [] as $commentIndex => [$authorType, $authorIndex, $message]) {
                    $author = $authorType === 'staff'
                        ? $staff[$authorIndex]
                        : $reporters[$sample['reporter']];

                    $comment = $ticket->comments()->create([
                        'user_id' => $author->id,
                        'message' => $message,
                    ]);

                    $commentAt = $createdAt->copy()->addHours(2 + $commentIndex);

                    $comment->timestamps = false;
                    $comment->forceFill([
                        'created_at' => $commentAt,
                        'updated_at' => $commentAt,
                    ])->saveQuietly();
                    $comment->timestamps = true;
                }
            }
        } finally {
            Auth::logout();
        }
    }

    /**
     * @return list<array{
     *     subject: string,
     *     description: string,
     *     category: string,
     *     priority: string,
     *     status: string,
     *     reporter: int,
     *     assignee: int|null,
     *     days_ago: int,
     *     comments?: list<array{0: string, 1: int, 2: string}>
     * }>
     */
    private function samples(): array
    {
        return [
            [
                'subject' => 'Tidak bisa login ke portal akademik',
                'description' => 'Sejak pagi saya tidak bisa masuk ke portal akademik. Halaman menampilkan pesan kredensial tidak valid padahal password sudah benar.',
                'category' => 'account-access',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 0,
                'assignee' => 0,
                'days_ago' => 1,
                'comments' => [
                    ['reporter', 0, 'Saya sudah mencoba reset password dua kali, tetapi tetap gagal masuk.'],
                    ['staff', 0, 'Akun Anda terkunci karena percobaan login berulang. Sudah kami buka kembali, silakan coba lagi.'],
                ],
            ],
            [
                'subject' => 'Printer lantai 2 tidak bisa mencetak',
                'description' => 'Printer di ruang administrasi lantai 2 menyala, tetapi setiap dokumen berhenti di antrean dan tidak keluar hasil cetak.',
                'category' => 'hardware',
                'priority' => 'medium',
                'status' => 'in_progress',
                'reporter' => 1,
                'assignee' => 1,
                'days_ago' => 2,
            ],
            [
                'subject' => 'Aplikasi absensi menampilkan error 500',
                'description' => 'Saat membuka menu rekap absensi, aplikasi menampilkan error 500. Masalah terjadi untuk semua karyawan di divisi saya.',
                'category' => 'software',
                'priority' => 'urgent',
                'status' => 'open',
                'reporter' => 0,
                'assignee' => 0,
                'days_ago' => 0,
                'comments' => [
                    ['staff', 0, 'Kami sedang memeriksa log server dan akan memperbarui perkembangan di tiket ini.'],
                ],
            ],
            [
                'subject' => 'WiFi ruang rapat sering terputus',
                'description' => 'Koneksi WiFi di ruang rapat utama sering terputus saat rapat online. Perangkat lain di lantai yang sama juga mengalami hal serupa.',
                'category' => 'network',
                'priority' => 'high',
                'status' => 'pending',
                'reporter' => 1,
                'assignee' => 0,
                'days_ago' => 4,
            ],
            [
                'subject' => 'Laptop mati total setelah update sistem',
                'description' => 'Setelah pembaruan sistem operasi kemarin, laptop tidak dapat menyala. Lampu indikator tetap menyala ketika charger terpasang.',
                'category' => 'hardware',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 3,
                'comments' => [
                    ['reporter', 0, 'Laptop masih dalam masa garansi dan dokumen penting ada di dalamnya.'],
                    ['staff', 0, 'Kami akan mencoba recovery data terlebih dahulu sebelum tindakan perangkat keras.'],
                ],
            ],
            [
                'subject' => 'Permintaan reset password email kampus',
                'description' => 'Saya lupa password email kampus dan nomor pemulihan sudah tidak aktif. Mohon bantuan untuk reset manual.',
                'category' => 'account-access',
                'priority' => 'low',
                'status' => 'resolved',
                'reporter' => 1,
                'assignee' => 0,
                'days_ago' => 5,
            ],
            [
                'subject' => 'Aplikasi e-learning lambat saat jam sibuk',
                'description' => 'Portal e-learning membutuhkan waktu lebih dari satu menit untuk membuka materi, terutama pukul 09.00-11.00.',
                'category' => 'software',
                'priority' => 'high',
                'status' => 'pending',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 7,
            ],
            [
                'subject' => 'Tidak bisa mengakses drive bersama',
                'description' => 'Folder drive bersama divisi keuangan tidak muncul di file explorer sejak pergantian jaringan kantor.',
                'category' => 'network',
                'priority' => 'medium',
                'status' => 'open',
                'reporter' => 1,
                'assignee' => null,
                'days_ago' => 6,
            ],
            [
                'subject' => 'Monitor berkedip saat digunakan',
                'description' => 'Monitor eksternal berkedip setiap beberapa menit. Kabel dan port sudah dicoba diganti namun masalah tetap terjadi.',
                'category' => 'hardware',
                'priority' => 'medium',
                'status' => 'closed',
                'reporter' => 0,
                'assignee' => 0,
                'days_ago' => 10,
            ],
            [
                'subject' => 'Instalasi ulang Microsoft Office',
                'description' => 'Microsoft Office pada komputer ruang pelatihan mengalami corrupt dan perlu instalasi ulang.',
                'category' => 'software',
                'priority' => 'low',
                'status' => 'closed',
                'reporter' => 1,
                'assignee' => 1,
                'days_ago' => 12,
            ],
            [
                'subject' => 'Email tidak masuk dari pengirim eksternal',
                'description' => 'Email dari mitra eksternal tidak masuk ke kotak masuk sejak kemarin, tetapi email internal berjalan normal.',
                'category' => 'network',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 14,
            ],
            [
                'subject' => 'Akun terkunci setelah salah password',
                'description' => 'Akun sistem kepegawaian terkunci setelah beberapa kali salah memasukkan password. Mohon dibuka kembali.',
                'category' => 'account-access',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 1,
                'assignee' => 0,
                'days_ago' => 16,
            ],
            [
                'subject' => 'Permintaan akses aplikasi keuangan',
                'description' => 'Saya membutuhkan akses baca ke modul laporan aplikasi keuangan untuk keperluan audit internal bulan ini.',
                'category' => 'general-request',
                'priority' => 'medium',
                'status' => 'pending',
                'reporter' => 0,
                'assignee' => 0,
                'days_ago' => 18,
            ],
            [
                'subject' => 'Proyektor ruang kelas tidak menyala',
                'description' => 'Proyektor di ruang kelas B tidak menyala saat tombol power ditekan. Lampu indikator berwarna merah berkedip.',
                'category' => 'hardware',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 1,
                'assignee' => 1,
                'days_ago' => 20,
            ],
            [
                'subject' => 'Bug validasi form pengajuan cuti',
                'description' => 'Form pengajuan cuti menerima tanggal selesai yang lebih awal dari tanggal mulai. Seharusnya validasi menolak data tersebut.',
                'category' => 'software',
                'priority' => 'urgent',
                'status' => 'closed',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 24,
                'comments' => [
                    ['reporter', 0, 'Langkah reproduksi sudah saya lampirkan pada deskripsi tiket.'],
                    ['staff', 1, 'Perbaikan sudah dirilis dan diuji di lingkungan staging.'],
                ],
            ],
            [
                'subject' => 'Koneksi VPN kantor gagal',
                'description' => 'VPN kantor menolak koneksi dengan pesan autentikasi gagal sejak pergantian sertifikat server.',
                'category' => 'network',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 1,
                'assignee' => 0,
                'days_ago' => 27,
            ],
            [
                'subject' => 'Permintaan pembuatan akun tamu',
                'description' => 'Mohon dibuatkan lima akun tamu untuk peserta workshop minggu depan dengan masa aktif tujuh hari.',
                'category' => 'account-access',
                'priority' => 'low',
                'status' => 'resolved',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 30,
            ],
            [
                'subject' => 'Server file tidak dapat diakses dari luar',
                'description' => 'Server file hanya dapat diakses dari jaringan kantor. Karyawan yang bekerja dari rumah tidak bisa membuka dokumen.',
                'category' => 'network',
                'priority' => 'urgent',
                'status' => 'closed',
                'reporter' => 1,
                'assignee' => 0,
                'days_ago' => 35,
            ],
            [
                'subject' => 'Aplikasi mobile error setelah pembaruan',
                'description' => 'Setelah pembaruan aplikasi mobile, halaman profil selalu menampilkan pesan kesalahan dan pengguna ter-logout otomatis.',
                'category' => 'software',
                'priority' => 'high',
                'status' => 'resolved',
                'reporter' => 0,
                'assignee' => 1,
                'days_ago' => 38,
            ],
            [
                'subject' => 'Penggantian keyboard rusak',
                'description' => 'Beberapa tombol pada keyboard tidak berfungsi. Mohon disiapkan penggantian keyboard cadangan.',
                'category' => 'hardware',
                'priority' => 'low',
                'status' => 'open',
                'reporter' => 1,
                'assignee' => null,
                'days_ago' => 40,
            ],
        ];
    }
}
