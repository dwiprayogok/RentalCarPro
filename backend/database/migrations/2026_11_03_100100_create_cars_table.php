<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 60);                   // Toyota
            $table->string('model', 80);                   // Innova Reborn
            $table->string('plate_number', 15)->unique();  // F 1234 RTY (dinormalisasi kapital)
            $table->enum('transmission', ['matic', 'manual'])->default('matic');
            $table->unsignedSmallInteger('year');
            $table->string('color', 40)->nullable();
            $table->unsignedTinyInteger('seats')->default(5);
            $table->string('fuel_type', 30)->nullable();

            // Tarif per hari (rupiah, integer). Tarif driver = total termasuk driver.
            $table->unsignedInteger('rate_self_drive');
            $table->unsignedInteger('rate_with_driver');
            $table->unsignedInteger('weekend_rate_self_drive')->nullable();
            $table->unsignedInteger('weekend_rate_with_driver')->nullable();
            $table->unsignedInteger('default_deposit')->default(0);

            $table->unsignedInteger('odometer')->default(0);
            $table->enum('status', ['tersedia', 'disewa', 'servis', 'nonaktif'])->default('tersedia');

            // Perawatan & dokumen kendaraan
            $table->date('tax_due_date')->nullable();          // pajak tahunan
            $table->date('stnk_due_date')->nullable();         // perpanjang STNK 5 tahunan
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_odometer')->nullable();
            $table->unsignedInteger('service_interval_km')->nullable();

            // Landing page (disiapkan sekarang, dipakai di phase landing)
            $table->string('slug', 120)->unique();
            $table->boolean('is_published')->default(false);
            $table->text('description')->nullable();
            $table->json('features')->nullable();          // ["AC", "Bluetooth", ...]

            $table->text('notes')->nullable();             // catatan internal, tidak pernah publik
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['is_published', 'status']);
        });

        Schema::create('car_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('public');  // foto armada memang untuk publik
            $table->string('path');
            $table->string('caption')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['car_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_photos');
        Schema::dropIfExists('cars');
    }
};
