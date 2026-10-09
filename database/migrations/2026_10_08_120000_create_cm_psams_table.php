<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── PSAM registry ────────────────────────────────────────────
        // One row per physical PSAM card. Metadata only — key material
        // is never stored here (roots live inside the PSAM and in the
        // encrypted lab keystore).
        Schema::create('cm_psams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('psam_number', 12)->unique('uq_psam_101'); // 12-digit
            $table->string('atr', 64)->nullable();                    // ATR hex
            $table->string('adf_aid', 32)->nullable();                // e.g. A00000000386980701
            $table->string('status', 32)->default('INITIALIZED');     // INITIALIZED/INSTALLED/ACTIVE/SUSPENDED/RETIRED
            $table->string('key_profile_id', 128)->nullable();        // e.g. hitee-lab-v1
            $table->json('root_key_versions')->nullable();            // root-key version map (no key bytes)
            $table->string('issuer_workstation_id', 128)->nullable();
            $table->string('issuer_operator_id', 128)->nullable();
            $table->timestamp('issued_at', 3)->nullable();
            $table->string('installed_device_id', 128)->nullable();   // cm_validator_devices.device_id
            $table->timestamp('installed_at', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps(3);

            $table->index('status');
            $table->index('installed_device_id');
        });

        // ── PSAM issuance operations (append-only audit) ─────────────
        Schema::create('cm_psam_issuance_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('psam_id')->nullable();
            $table->string('psam_number', 12);
            $table->string('operation_mode', 16);                     // INITIALIZE/REINITIALIZE
            $table->string('result', 16);                             // SUCCESS/FAILED
            $table->string('failed_step', 64)->nullable();
            $table->string('error', 500)->nullable();
            $table->string('status_word', 8)->nullable();
            $table->json('steps')->nullable();                        // completed step names
            $table->string('atr', 64)->nullable();
            $table->string('workstation_id', 128)->nullable();
            $table->string('operator_id', 128)->nullable();
            $table->timestamp('issued_at', 3)->nullable();            // desktop ceremony time
            $table->timestamps(3);

            $table->index('psam_number');
            $table->index('result');
            $table->foreign('psam_id')->references('id')->on('cm_psams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_psam_issuance_operations');
        Schema::dropIfExists('cm_psams');
    }
};
