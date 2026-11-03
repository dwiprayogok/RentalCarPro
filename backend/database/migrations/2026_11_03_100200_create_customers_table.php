<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20);                   // format E.164: +62812...

            // NIK: ciphertext (encrypted cast) + hash untuk pencarian/unik + 4 digit untuk masking.
            $table->text('nik_encrypted');
            $table->char('nik_hash', 64)->unique();
            $table->char('nik_last4', 4);

            // SIM (wajib untuk Lepas Kunci, divalidasi di layer aplikasi)
            $table->string('sim_number', 30)->nullable();
            $table->string('sim_type', 10)->nullable();    // A, B1, ...
            $table->date('sim_expires_at')->nullable();

            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 40)->nullable();

            // Dokumen di disk PRIVAT; diakses lewat signed URL.
            $table->string('ktp_photo_path')->nullable();
            $table->string('sim_photo_path')->nullable();

            $table->enum('risk_status', ['lancar', 'perhatian', 'blacklist'])->default('lancar');
            $table->text('risk_note')->nullable();
            $table->timestamp('risk_updated_at')->nullable();
            $table->foreignId('risk_updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('risk_status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
