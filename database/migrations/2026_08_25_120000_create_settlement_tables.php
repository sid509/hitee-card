<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Settlement batches ────────────────────────────────────────
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('settlement_date');
            $table->string('status', 32)->default('OPEN'); // OPEN, CALCULATED, APPROVED, PAID, CLOSED
            $table->integer('total_trips')->default(0);
            $table->bigInteger('total_fare_minor_units')->default(0);
            $table->bigInteger('total_commission_minor_units')->default(0);
            $table->bigInteger('total_payout_minor_units')->default(0);
            $table->decimal('commission_rate', 5, 4)->default(0.1000); // 10%
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at', 3)->nullable();
            $table->timestamp('approved_at', 3)->nullable();
            $table->timestamp('paid_at', 3)->nullable();
            $table->timestamp('closed_at', 3)->nullable();
            $table->timestamps(3);

            $table->unique('settlement_date', 'uq_settlement_date');
        });

        // ── Settlement entries (per device/operator) ──────────────────
        Schema::create('settlement_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->foreign('batch_id', 'fk_settle_entry_batch')->references('id')->on('settlement_batches')->onDelete('cascade');
            $table->uuid('device_id')->nullable();
            $table->string('operator_id', 128)->nullable();   // merchant/operator ID
            $table->integer('trip_count')->default(0);
            $table->bigInteger('fare_minor_units')->default(0);
            $table->bigInteger('commission_minor_units')->default(0);
            $table->bigInteger('payout_minor_units')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps(3);

            $table->index(['batch_id', 'device_id'], 'idx_settle_entry_batch_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_entries');
        Schema::dropIfExists('settlement_batches');
    }
};
