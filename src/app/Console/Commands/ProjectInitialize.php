<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProjectInitialize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Project Initialization (idempoten — aman dijalankan berulang kali)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Perintah ini tidak pernah menghapus data.
        $this->call('migrate', ['--force' => true]);

        // Hanya generate permission; policy dikelola manual agar tidak tertimpa.
        $this->call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);

        $this->call('db:seed', ['--force' => true]);

        $this->call('optimize:clear');

        return self::SUCCESS;
    }
}
