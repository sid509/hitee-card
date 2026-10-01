<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Card registry ──────────────────────────────────────────────
        Schema::create('cm_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uid', 32)->unique('uq_101');          // hex card UID
            $table->string('card_number', 20)->unique('uq_102');   // 16-digit
            $table->string('card_number_prefix', 15)->nullable();
            $table->bigInteger('card_number_sequence')->nullable();
            $table->string('card_type_code', 4)->default('0100');
            $table->string('card_type_label', 30)->default('STANDARD');
            $table->string('status', 48)->default('NEW');
            $table->json('metadata')->nullable();
            $table->string('card_structure_version', 32)->default('1.13');
            $table->string('key_profile_version', 128)->default('hitee-lab-v1');
            $table->uuid('current_initialization_operation_id')->nullable();
            $table->string('environment', 16)->default('LAB');
            $table->boolean('production_eligible')->default(false);
            $table->string('installed_key_profile_version', 128)->nullable();
            $table->string('installed_card_structure_version', 32)->nullable();
            $table->timestamp('last_card_verification_at', 3)->nullable();
            $table->uuid('customer_id')->nullable();
            $table->timestamp('issued_at', 3)->nullable();
            $table->timestamp('activated_at', 3)->nullable();
            $table->timestamp('blocked_at', 3)->nullable();
            $table->string('lifecycle_reason', 500)->nullable();
            $table->uuid('current_issuance_operation_id')->nullable();
            $table->uuid('current_replacement_operation_id')->nullable();
            $table->uuid('replaces_card_id')->nullable();
            $table->uuid('replaced_by_card_id')->nullable();
            $table->timestamp('initialized_at', 3)->nullable();
            $table->timestamps(3);

            $table->index('uid');
            $table->index('status');
            $table->index('customer_id');
        });

        // ── Card number allocation ─────────────────────────────────────
        Schema::create('cm_card_number_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('prefix', 15);
            $table->bigInteger('last_sequence')->default(0);
            $table->timestamps(3);
            $table->unique('prefix');
        });

        // ── Terminal transaction sequence ──────────────────────────────
        Schema::create('cm_terminal_transaction_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('last_sequence')->default(0);
        });

        // ── Initialization operations ──────────────────────────────────
        Schema::create('cm_card_initialization_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_1')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('idempotency_key', 128)->unique('uq_103');
            $table->string('expected_uid', 32);
            $table->string('status', 32)->default('KEYS_PENDING');
            $table->string('current_step', 64)->default('KEYS_PENDING');
            $table->string('last_successful_step', 64)->nullable();
            $table->string('last_attempted_step', 64)->nullable();
            $table->boolean('physical_state_uncertain')->default(false);
            $table->integer('lock_version')->default(0);
            $table->string('workstation_id', 128);
            $table->string('card_structure_version', 32);
            $table->string('key_profile_version', 128);
            $table->string('key_service_request_id', 128)->nullable()->unique('uq_104');
            $table->json('key_manifest')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('operation_mode', 32)->default('INITIALIZE');
            $table->string('write_mode', 16)->default('LAB');
            $table->string('authorization_key_profile_version', 128)->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('operator_confirmation_sha256', 64)->nullable();
            $table->json('profile_snapshot')->nullable();
            $table->timestamp('started_at', 3)->useCurrent();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamp('failed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["card_id", "created_at"], "idx_cm_card_initialization_operations_card_created");
        });

        Schema::create('cm_card_initialization_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_2')->references('id')->on('cm_card_initialization_operations')->onDelete('cascade');
            $table->string('step', 64);
            $table->integer('sequence_no');
            $table->string('workstation_id', 128);
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->unique(["operation_id", "step"], "uniq_cm_card_initialization_checkpoints_step");
            $table->unique(["operation_id", "sequence_no"], "uniq_cm_card_initialization_checkpoints_seq");
        });

        Schema::create('cm_initialization_key_envelopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id')->unique('uq_105');
            $table->foreign('operation_id', 'fk_cm_3')->references('id')->on('cm_card_initialization_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128)->unique('uq_106');
            $table->string('recipient_key_id', 128);
            $table->string('recipient_public_key_fingerprint', 64);
            $table->integer('envelope_version')->default(1);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->integer('delivery_count')->default(0);
            $table->timestamp('first_delivered_at', 3)->nullable();
            $table->timestamp('last_delivered_at', 3)->nullable();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Wallet recharge operations ─────────────────────────────────
        Schema::create('cm_wallet_recharge_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_4')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('idempotency_key', 128)->unique('uq_107');
            $table->string('expected_uid', 32);
            $table->char('card_number', 16);
            $table->string('workstation_id', 128);
            $table->string('status', 32)->default('KEYS_PENDING');
            $table->string('last_successful_step', 40)->nullable();
            $table->string('last_attempted_step', 40)->nullable();
            $table->integer('lock_version')->default(0);
            $table->integer('amount_minor_units');
            $table->string('purpose', 40)->default('LAB_RECHARGE');
            $table->uuid('replacement_operation_id')->nullable();
            $table->char('terminal_number_hex', 12);
            $table->string('key_profile_version', 128);
            $table->json('profile_snapshot')->nullable();
            $table->string('key_service_request_id', 128)->nullable()->unique('uq_108');
            $table->json('key_manifest')->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('operator_confirmation_sha256', 64)->nullable();
            $table->boolean('credit_command_attempted')->default(false);
            $table->boolean('physical_state_uncertain')->default(false);
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->integer('online_counter_before')->nullable();
            $table->char('transaction_datetime', 14)->nullable();
            $table->boolean('mac1_verified')->nullable();
            $table->char('tac_hex', 8)->nullable();
            $table->boolean('tac_verified')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->timestamp('started_at', 3)->useCurrent();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamp('failed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["card_id", "created_at"], "idx_cm_wallet_recharge_operations_card_created");
        });

        Schema::create('cm_wallet_recharge_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_5')->references('id')->on('cm_wallet_recharge_operations')->onDelete('cascade');
            $table->string('step', 40);
            $table->integer('sequence_no');
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(["operation_id", "step"], "uniq_cm_wallet_recharge_checkpoints_step");
        });

        Schema::create('cm_wallet_recharge_key_envelopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id')->unique('uq_109');
            $table->foreign('operation_id', 'fk_cm_6')->references('id')->on('cm_wallet_recharge_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128)->unique('uq_110');
            $table->string('recipient_key_id', 128);
            $table->string('recipient_public_key_fingerprint', 64);
            $table->integer('envelope_version')->default(1);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->integer('delivery_count')->default(0);
            $table->timestamp('first_delivered_at', 3)->nullable();
            $table->timestamp('last_delivered_at', 3)->nullable();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Wallet debit operations ────────────────────────────────────
        Schema::create('cm_wallet_debit_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_7')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('idempotency_key', 128)->unique('uq_111');
            $table->string('expected_uid', 32);
            $table->char('card_number', 16);
            $table->string('workstation_id', 128);
            $table->string('status', 32)->default('KEYS_PENDING');
            $table->string('last_successful_step', 40)->nullable();
            $table->string('last_attempted_step', 40)->nullable();
            $table->integer('lock_version')->default(0);
            $table->integer('amount_minor_units');
            $table->char('terminal_number_hex', 12);
            $table->bigInteger('terminal_transaction_sequence');
            $table->string('key_profile_version', 128);
            $table->json('profile_snapshot')->nullable();
            $table->string('key_service_request_id', 128)->nullable()->unique('uq_112');
            $table->json('key_manifest')->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('operator_confirmation_sha256', 64)->nullable();
            $table->boolean('debit_command_attempted')->default(false);
            $table->boolean('physical_state_uncertain')->default(false);
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->integer('offline_counter_before')->nullable();
            $table->integer('offline_counter_after')->nullable();
            $table->char('transaction_datetime', 14)->nullable();
            $table->char('tac_hex', 8)->nullable();
            $table->boolean('tac_verified')->nullable();
            $table->char('mac2_hex', 8)->nullable();
            $table->boolean('mac2_verified')->nullable();
            $table->boolean('proof_retrieved')->default(false);
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->timestamp('started_at', 3)->useCurrent();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamp('failed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["card_id", "created_at"], "idx_cm_wallet_debit_operations_card_created");
        });

        Schema::create('cm_wallet_debit_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_8')->references('id')->on('cm_wallet_debit_operations')->onDelete('cascade');
            $table->string('step', 40);
            $table->integer('sequence_no');
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(["operation_id", "step"], "uniq_cm_wallet_debit_checkpoints_step");
        });

        Schema::create('cm_wallet_debit_key_envelopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id')->unique('uq_113');
            $table->foreign('operation_id', 'fk_cm_9')->references('id')->on('cm_wallet_debit_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128)->unique('uq_114');
            $table->string('recipient_key_id', 128);
            $table->string('recipient_public_key_fingerprint', 64);
            $table->integer('envelope_version')->default(1);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->integer('delivery_count')->default(0);
            $table->timestamp('first_delivered_at', 3)->nullable();
            $table->timestamp('last_delivered_at', 3)->nullable();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Wallet recharge reversal operations ────────────────────────
        Schema::create('cm_wallet_recharge_reversal_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('original_recharge_operation_id');
            $table->foreign('original_recharge_operation_id', 'fk_cm_10')->references('id')->on('cm_wallet_recharge_operations')->onDelete('restrict');
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_11')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('idempotency_key', 128)->unique('uq_115');
            $table->string('expected_uid', 32);
            $table->char('card_number', 16);
            $table->string('workstation_id', 128);
            $table->string('status', 32)->default('KEYS_PENDING');
            $table->string('last_successful_step', 40)->nullable();
            $table->string('last_attempted_step', 40)->nullable();
            $table->integer('lock_version')->default(0);
            $table->integer('amount_minor_units');
            $table->timestamp('original_recharge_completed_at', 3);
            $table->char('terminal_number_hex', 12);
            $table->bigInteger('terminal_transaction_sequence');
            $table->string('key_profile_version', 128);
            $table->json('profile_snapshot')->nullable();
            $table->string('key_service_request_id', 128)->nullable()->unique('uq_116');
            $table->json('key_manifest')->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('operator_confirmation_sha256', 64)->nullable();
            $table->boolean('debit_command_attempted')->default(false);
            $table->boolean('physical_state_uncertain')->default(false);
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->integer('offline_counter_before')->nullable();
            $table->integer('offline_counter_after')->nullable();
            $table->char('transaction_datetime', 14)->nullable();
            $table->char('tac_hex', 8)->nullable();
            $table->boolean('tac_verified')->nullable();
            $table->char('mac2_hex', 8)->nullable();
            $table->boolean('mac2_verified')->nullable();
            $table->boolean('proof_retrieved')->default(false);
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->timestamp('started_at', 3)->useCurrent();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamp('failed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["card_id", "created_at"], "idx_cm_wallet_recharge_reversal_operations_card_created");
        });

        Schema::create('cm_wallet_recharge_reversal_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_12')->references('id')->on('cm_wallet_recharge_reversal_operations')->onDelete('cascade');
            $table->string('step', 40);
            $table->integer('sequence_no');
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(["operation_id", "step"], "uniq_cm_wallet_recharge_reversal_checkpoints_step");
        });

        Schema::create('cm_wallet_recharge_reversal_key_envelopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id')->unique('uq_117');
            $table->foreign('operation_id', 'fk_cm_13')->references('id')->on('cm_wallet_recharge_reversal_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128)->unique('uq_118');
            $table->string('recipient_key_id', 128);
            $table->string('recipient_public_key_fingerprint', 64);
            $table->integer('envelope_version')->default(1);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->integer('delivery_count')->default(0);
            $table->timestamp('first_delivered_at', 3)->nullable();
            $table->timestamp('last_delivered_at', 3)->nullable();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Customers ──────────────────────────────────────────────────
        Schema::create('cm_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_number', 32)->unique('uq_119');
            $table->string('customer_type', 24)->default('NAMED');
            $table->string('full_name', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps(3);

            $table->index('customer_number');
            $table->index('full_name');
        });

        // ── Card issuance operations ───────────────────────────────────
        Schema::create('cm_card_issuance_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_14')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->uuid('customer_id')->nullable();
            $table->foreign('customer_id', 'fk_cm_15')->references('id')->on('cm_customers')->onDelete('set null');
            $table->string('idempotency_key', 128)->unique('uq_120');
            $table->string('expected_uid', 32);
            $table->string('card_number', 20);
            $table->string('workstation_id', 128);
            $table->string('issuance_mode', 24);
            $table->string('card_category', 32);
            $table->string('card_type_code', 4);
            $table->string('application_type', 2);
            $table->integer('deposit_minor_units')->default(0);
            $table->date('enable_date');
            $table->date('expiry_date');
            $table->string('status', 32)->default('KEYS_PENDING');
            $table->string('key_profile_version', 128);
            $table->json('profile_snapshot')->nullable();
            $table->string('expected_confirmation_sha256', 64)->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('last_successful_step', 64)->nullable();
            $table->string('last_attempted_step', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->boolean('physical_state_uncertain')->default(false);
            $table->integer('lock_version')->default(0);
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["card_id", "created_at"], "idx_cm_card_issuance_operations_card_created");
            $table->index('status');
        });

        Schema::create('cm_card_issuance_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_16')->references('id')->on('cm_card_issuance_operations')->onDelete('cascade');
            $table->string('step', 64);
            $table->integer('sequence_no');
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(["operation_id", "step"], "uniq_cm_card_issuance_checkpoints_step");
        });

        Schema::create('cm_card_issuance_key_envelopes', function (Blueprint $table) {
            $table->uuid('operation_id')->primary();
            $table->foreign('operation_id', 'fk_cm_17')->references('id')->on('cm_card_issuance_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128);
            $table->string('recipient_key_id', 128);
            $table->string('recipient_fingerprint', 64);
            $table->string('envelope_version', 32);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Card lifecycle events ──────────────────────────────────────
        Schema::create('cm_card_lifecycle_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('card_id');
            $table->foreign('card_id', 'fk_cm_18')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->string('reason', 500)->nullable();
            $table->string('workstation_id', 128);
            $table->string('operator_id', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(["card_id", "created_at"], "idx_cm_card_lifecycle_events_card_created");
        });

        // ── Card replacement operations ────────────────────────────────
        Schema::create('cm_card_replacement_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('new_card_id');
            $table->foreign('new_card_id', 'fk_cm_19')->references('id')->on('cm_cards')->onDelete('restrict');
            $table->string('old_card_reference', 32);
            $table->uuid('old_card_id')->nullable();
            $table->string('reason', 32);
            $table->string('notes', 500)->nullable();
            $table->integer('approved_transfer_amount_minor_units')->default(0);
            $table->string('idempotency_key', 128)->unique('uq_121');
            $table->string('expected_uid', 32);
            $table->string('card_number', 20);
            $table->string('workstation_id', 128);
            $table->string('card_type_code', 4);
            $table->string('application_type', 2);
            $table->integer('deposit_minor_units')->default(0);
            $table->date('enable_date');
            $table->date('expiry_date');
            $table->string('status', 32)->default('CREATED');
            $table->string('transfer_status', 32)->default('NOT_REQUIRED');
            $table->string('key_profile_version', 128);
            $table->json('profile_snapshot')->nullable();
            $table->json('balance_evidence')->nullable();
            $table->string('expected_confirmation_sha256', 64)->nullable();
            $table->timestamp('physical_write_authorized_at', 3)->nullable();
            $table->string('last_successful_step', 64)->nullable();
            $table->string('last_attempted_step', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->boolean('physical_state_uncertain')->default(false);
            $table->integer('lock_version')->default(0);
            $table->uuid('linked_recharge_operation_id')->nullable();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(["new_card_id", "created_at"], "newcard_created_idx");
            $table->index('status');
        });

        Schema::create('cm_card_replacement_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreign('operation_id', 'fk_cm_20')->references('id')->on('cm_card_replacement_operations')->onDelete('cascade');
            $table->string('step', 64);
            $table->integer('sequence_no');
            $table->json('result_metadata')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(["operation_id", "step"], "uniq_cm_card_replacement_checkpoints_step");
        });

        Schema::create('cm_card_replacement_key_envelopes', function (Blueprint $table) {
            $table->uuid('operation_id')->primary();
            $table->foreign('operation_id', 'fk_cm_21')->references('id')->on('cm_card_replacement_operations')->onDelete('cascade');
            $table->string('key_service_request_id', 128);
            $table->string('recipient_key_id', 128);
            $table->string('recipient_fingerprint', 64);
            $table->string('envelope_version', 32);
            $table->json('envelope');
            $table->timestamp('expires_at', 3);
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamps(3);
        });

        // ── Audit events (append-only) ─────────────────────────────────
        Schema::create('cm_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_id', 128)->nullable();
            $table->string('action', 128);
            $table->string('entity_type', 64)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->string('operation_type', 32)->nullable();
            $table->uuid('operation_id')->nullable();
            $table->uuid('card_id')->nullable();
            $table->string('card_number', 20)->nullable();
            $table->string('card_uid', 32)->nullable();
            $table->string('workstation_id', 128)->nullable();
            $table->string('operator_id', 128)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('http_path', 255)->nullable();
            $table->string('result', 20)->nullable();
            $table->json('operation_state')->nullable();
            $table->timestamp('recorded_at', 3)->useCurrent();

            $table->index(["operation_type", "recorded_at"], "optype_recorded_idx");
            $table->index(["card_id", "recorded_at"], "card_recorded_idx");
            $table->index(["workstation_id", "recorded_at"], "ws_recorded_idx");
        });

        // ── Idempotency keys ───────────────────────────────────────────
        Schema::create('cm_idempotency_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 128);
            $table->string('workstation_id', 128);
            $table->string('path', 255);
            $table->json('response_payload');
            $table->integer('response_status');
            $table->timestamp('created_at', 3)->useCurrent();

            $table->unique(["idempotency_key", "workstation_id", "path"], "idem_uniq");
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_idempotency_keys');
        Schema::dropIfExists('cm_audit_events');
        Schema::dropIfExists('cm_card_replacement_key_envelopes');
        Schema::dropIfExists('cm_card_replacement_checkpoints');
        Schema::dropIfExists('cm_card_replacement_operations');
        Schema::dropIfExists('cm_card_lifecycle_events');
        Schema::dropIfExists('cm_card_issuance_key_envelopes');
        Schema::dropIfExists('cm_card_issuance_checkpoints');
        Schema::dropIfExists('cm_card_issuance_operations');
        Schema::dropIfExists('cm_customers');
        Schema::dropIfExists('cm_wallet_recharge_reversal_key_envelopes');
        Schema::dropIfExists('cm_wallet_recharge_reversal_checkpoints');
        Schema::dropIfExists('cm_wallet_recharge_reversal_operations');
        Schema::dropIfExists('cm_wallet_debit_key_envelopes');
        Schema::dropIfExists('cm_wallet_debit_checkpoints');
        Schema::dropIfExists('cm_wallet_debit_operations');
        Schema::dropIfExists('cm_wallet_recharge_key_envelopes');
        Schema::dropIfExists('cm_wallet_recharge_checkpoints');
        Schema::dropIfExists('cm_wallet_recharge_operations');
        Schema::dropIfExists('cm_initialization_key_envelopes');
        Schema::dropIfExists('cm_card_initialization_checkpoints');
        Schema::dropIfExists('cm_card_initialization_operations');
        Schema::dropIfExists('cm_terminal_transaction_sequences');
        Schema::dropIfExists('cm_card_number_allocations');
        Schema::dropIfExists('cm_cards');
    }
};
