<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengeluaran operasional. car_id NULL = pengeluaran umum (kantor, gaji, dsb.).
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('title', 150);
            $table->date('spent_at');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20)->nullable();
            $table->string('vendor', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['spent_at', 'deleted_at']);
            $table->index(['car_id', 'spent_at']);
            $table->index(['category', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
