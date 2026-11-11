<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('subject', 255);
            $table->text('description');
            $table->foreignId('category_id')->constrained('ticket_categories')->restrictOnDelete();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Index untuk pencarian & filter laporan
            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
