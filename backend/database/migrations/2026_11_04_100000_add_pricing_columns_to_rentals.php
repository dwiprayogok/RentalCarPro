<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Rincian tarif per hari saat sewa dibuat (snapshot, untuk nota).
            $table->json('price_breakdown')->nullable()->after('rental_amount');
            // Indeks bantu filter "belum lunas" & pencarian per tanggal dibuat.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('price_breakdown');
        });
    }
};
