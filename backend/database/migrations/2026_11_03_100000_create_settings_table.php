<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();          // tarif, denda, jadwal, usaha, landing
            $table->string('key', 100)->unique();          // mis. late_fee_per_hour
            $table->json('value')->nullable();             // disimpan JSON agar tipe terjaga
            $table->string('type', 20)->default('string'); // int, bool, string, json
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);  // boleh tampil di Public API
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Penomoran dokumen atomik (TRX-2026-0001). Diambil dengan SELECT ... FOR UPDATE.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('settings');
    }
};
