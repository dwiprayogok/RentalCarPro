<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index untuk agregasi laporan & dashboard. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_payments', function (Blueprint $table) {
            // Laporan pembayaran: rentang paid_at + filter jenis/metode, tanpa membaca baris lain.
            $table->index(['paid_at', 'type', 'method', 'amount'], 'rental_payments_report_idx');
        });
        Schema::table('rentals', function (Blueprint $table) {
            // Piutang (status + perbandingan kolom) & pendapatan per mobil per periode.
            $table->index(['status', 'paid_amount', 'total_amount'], 'rentals_receivable_idx');
            $table->index(['car_id', 'start_at'], 'rentals_car_start_idx');
        });
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->index(['performed_at', 'car_id', 'type', 'cost'], 'maintenance_report_idx');
        });
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('rental_payments', fn (Blueprint $t) => $t->dropIndex('rental_payments_report_idx'));
        Schema::table('rentals', function (Blueprint $t) {
            $t->dropIndex('rentals_receivable_idx');
            $t->dropIndex('rentals_car_start_idx');
        });
        Schema::table('maintenance_records', fn (Blueprint $t) => $t->dropIndex('maintenance_report_idx'));
        Schema::table('booking_requests', fn (Blueprint $t) => $t->dropIndex(['phone']));
    }
};
