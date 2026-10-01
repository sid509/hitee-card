<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code-review hardening columns.
 *
 * - cm_idempotency_keys.request_hash — guards against reusing an
 *   Idempotency-Key with a different payload (the middleware stores the
 *   request hash on the reservation row and rejects mismatches).
 * - cm_validator_trips.settlement_batch_id — links each settled trip to the
 *   batch that paid it out, so late-synced trips are picked up by the next
 *   run and already-settled trips are never double-counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cm_idempotency_keys', function (Blueprint $table) {
            $table->string('request_hash', 64)->nullable()->after('path');
        });

        Schema::table('cm_validator_trips', function (Blueprint $table) {
            $table->uuid('settlement_batch_id')->nullable()->after('reconciliation_status');
            $table->index('settlement_batch_id', 'idx_cm_validator_trips_settlement_batch');
        });
    }

    public function down(): void
    {
        Schema::table('cm_validator_trips', function (Blueprint $table) {
            $table->dropIndex('idx_cm_validator_trips_settlement_batch');
            $table->dropColumn('settlement_batch_id');
        });

        Schema::table('cm_idempotency_keys', function (Blueprint $table) {
            $table->dropColumn('request_hash');
        });
    }
};
