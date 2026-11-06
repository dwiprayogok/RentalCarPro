<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemilik mobil titipan + aturan bagi hasil per pemilik.
        Schema::create('car_owners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('bank_name', 60)->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            // kotor = % dari pendapatan diterima; bersih = % dari (pendapatan − perawatan − pengeluaran mobil).
            $table->string('share_basis', 10)->default('kotor');
            $table->decimal('share_percent', 5, 2);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
        });

        Schema::table('cars', function (Blueprint $table) {
            // NULL = milik rental sendiri.
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('car_owners')->restrictOnDelete();
        });

        // Setoran bagi hasil per periode. Angka di-snapshot saat dibuat agar slip tidak berubah.
        Schema::create('owner_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('owner_id')->constrained('car_owners')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('share_basis', 10);
            $table->decimal('share_percent', 5, 2);
            $table->bigInteger('income');
            $table->bigInteger('maintenance');
            $table->bigInteger('expenses');
            $table->bigInteger('base_amount');
            $table->bigInteger('owner_amount');
            $table->bigInteger('business_amount');
            $table->json('lines');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_method', 20)->nullable();
            $table->string('paid_reference', 100)->nullable();
            $table->string('paid_proof_path')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'period_from', 'period_to']);
            $table->index(['paid_at', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_settlements');
        Schema::table('cars', fn (Blueprint $table) => $table->dropConstrainedForeignId('owner_id'));
        Schema::dropIfExists('car_owners');
    }
};
