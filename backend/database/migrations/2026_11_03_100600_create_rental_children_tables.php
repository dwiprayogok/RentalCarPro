<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['dp', 'pelunasan', 'refund', 'deposit', 'deposit_kembali', 'deposit_potong']);
            $table->enum('method', ['tunai', 'transfer', 'qris'])->nullable(); // null untuk deposit_potong
            $table->unsignedBigInteger('amount');          // selalu positif; arah ditentukan type
            $table->dateTime('paid_at');
            $table->string('reference_number', 100)->nullable();
            $table->string('proof_path')->nullable();       // disk privat
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rental_id', 'type']);
            $table->index('paid_at');                       // laporan per tanggal bayar
        });

        Schema::create('rental_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['serah_terima', 'kembali']);
            $table->unsignedInteger('odometer');
            $table->unsignedTinyInteger('fuel_level');     // persen 0-100
            $table->json('checklist')->nullable();          // {"ban": "baik", "lecet": "ada", ...}
            $table->json('photos')->nullable();             // daftar path di disk privat
            $table->text('notes')->nullable();
            $table->dateTime('inspected_at');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rental_id', 'type']);
        });

        Schema::create('rental_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['bbm', 'kebersihan', 'kelebihan_km', 'kerusakan', 'lainnya']);
            $table->unsignedBigInteger('amount');
            $table->text('reason');                         // wajib
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rental_id', 'type']);
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete(); // kerusakan dari sewa
            $table->enum('type', ['servis_rutin', 'perbaikan', 'ban', 'pajak', 'stnk', 'lainnya']);
            $table->string('title');
            $table->date('performed_at');
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedBigInteger('cost')->default(0);
            $table->string('vendor')->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedInteger('next_due_odometer')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['car_id', 'performed_at']);
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('rental_fees');
        Schema::dropIfExists('rental_inspections');
        Schema::dropIfExists('rental_payments');
    }
};
