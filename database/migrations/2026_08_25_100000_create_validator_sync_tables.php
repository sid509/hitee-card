<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Validator devices ─────────────────────────────────────────
        Schema::create('cm_validator_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_id', 128)->unique();        // hardware/device identifier
            $table->string('vehicle_id', 128)->nullable();
            $table->string('route_id', 128)->nullable();
            $table->string('terminal_number_hex', 12)->nullable();
            $table->string('status', 32)->default('REGISTERED'); // REGISTERED, ACTIVE, SUSPENDED, DECOMMISSIONED
            $table->string('api_token', 128)->nullable();       // device auth token (hashed)
            $table->string('firmware_version', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_heartbeat_at', 3)->nullable();
            $table->timestamp('registered_at', 3)->useCurrent();
            $table->timestamps(3);

            $table->index('device_id');
        });

        // ── Trips synced from validators ──────────────────────────────
        Schema::create('cm_validator_trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->foreign('device_id', 'fk_vtrips_device')->references('id')->on('cm_validator_devices')->onDelete('cascade');
            $table->string('trip_id', 128);                     // validator-generated trip ID
            $table->string('card_uid', 32);
            $table->string('card_type', 32)->nullable();
            $table->timestamp('tap_in_at', 3)->nullable();
            $table->decimal('tap_in_lat', 10, 7)->nullable();
            $table->decimal('tap_in_lng', 10, 7)->nullable();
            $table->timestamp('tap_out_at', 3)->nullable();
            $table->decimal('tap_out_lat', 10, 7)->nullable();
            $table->decimal('tap_out_lng', 10, 7)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->decimal('fare_amount', 10, 2)->nullable();
            $table->integer('fare_minor_units')->nullable();
            $table->integer('balance_before')->nullable();
            $table->integer('balance_after')->nullable();
            $table->integer('offline_counter')->nullable();
            $table->string('transaction_datetime', 14)->nullable();
            $table->string('terminal_transaction_sequence', 32)->nullable();
            $table->string('reconciliation_status', 32)->default('none'); // none, success, failed, ambiguous
            $table->string('sync_status', 32)->default('SYNCED'); // PENDING, SYNCED, CONFLICT
            $table->string('idempotency_key', 128)->unique('uq_vtrips_idem');
            $table->timestamps(3);

            $table->index(['device_id', 'tap_in_at'], 'idx_vtrips_device_tapin');
            $table->index(['card_uid', 'tap_in_at'], 'idx_vtrips_card_tapin');
        });

        // ── Blocklist entries ─────────────────────────────────────────
        Schema::create('cm_blocklist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('card_uid', 32)->unique('uq_blocklist_uid');
            $table->string('card_number', 20)->nullable();
            $table->string('reason', 256);                      // LOST, STOLEN, FRAUD, EXPIRED, DAMAGED
            $table->string('status', 32)->default('ACTIVE');    // ACTIVE, LIFTED
            $table->timestamp('effective_at', 3)->useCurrent();
            $table->timestamp('lifted_at', 3)->nullable();
            $table->uuid('source_operation_id')->nullable();    // operation that triggered the block
            $table->string('source', 32)->default('MANUAL');    // MANUAL, SYSTEM, ISSUER
            $table->timestamps(3);

            $table->index(['status', 'effective_at'], 'idx_blocklist_status_date');
        });

        // ── Blocklist sync cursors (per-device) ───────────────────────
        Schema::create('cm_blocklist_cursors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->foreign('device_id', 'fk_blcursor_device')->references('id')->on('cm_validator_devices')->onDelete('cascade');
            $table->timestamp('last_synced_at', 3)->nullable();
            $table->integer('last_synced_sequence')->default(0);
            $table->timestamps(3);

            $table->unique('device_id', 'uq_blcursor_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_blocklist_cursors');
        Schema::dropIfExists('cm_blocklist_entries');
        Schema::dropIfExists('cm_validator_trips');
        Schema::dropIfExists('cm_validator_devices');
    }
};
