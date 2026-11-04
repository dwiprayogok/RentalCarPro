<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // TRX-2026-0001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booking_request_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->enum('service_type', ['lepas_kunci', 'dengan_driver']);
            // "terlambat" dihitung, tidak disimpan.
            $table->enum('status', ['booking', 'aktif', 'selesai', 'batal'])->default('booking');

            // Jadwal (UTC)
            $table->dateTime('start_at');
            $table->dateTime('planned_end_at');
            $table->dateTime('picked_up_at')->nullable();  // serah terima aktual
            $table->dateTime('returned_at')->nullable();   // kembali aktual

            // Snapshot harga saat transaksi dibuat (rupiah, integer)
            $table->unsignedInteger('daily_rate');
            $table->unsignedSmallInteger('billed_days');
            $table->unsignedBigInteger('rental_amount');   // sewa = billed_days x tarif (+ akhir pekan)
            $table->unsignedBigInteger('late_fee')->default(0);          // final saat kembali
            $table->unsignedSmallInteger('late_extra_days')->default(0); // hari tambahan karena terlambat
            $table->unsignedBigInteger('extra_fees_total')->default(0);  // jumlah rental_fees
            $table->unsignedBigInteger('discount')->default(0);
            $table->string('discount_reason')->nullable();
            $table->unsignedBigInteger('total_amount');    // sewa + denda + biaya - diskon

            // Cache agregat dari rental_payments (diperbarui oleh service)
            $table->bigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('deposit_required')->default(0);
            $table->bigInteger('deposit_held')->default(0);

            // Override blacklist (hanya owner, alasan wajib)
            $table->foreignId('blacklist_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('blacklist_override_reason')->nullable();

            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Index untuk cek bentrok jadwal & daftar
            $table->index(['car_id', 'status', 'start_at', 'planned_end_at'], 'rentals_car_schedule_idx');
            $table->index(['driver_id', 'status', 'start_at', 'planned_end_at'], 'rentals_driver_schedule_idx');
            $table->index(['status', 'planned_end_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
