<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('card_uid', 32)->nullable()->unique('uq_101');
            $table->string('card_number', 20)->unique('uq_102');
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
            $table->string('hwid')->nullable()->unique();
            $table->string('hitee_card_number', 16)->nullable()->unique();
            $table->boolean('is_currently_active')->default(false);
            $table->boolean('is_physical')->default(true);
            $table->boolean('is_personalized')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->softDeletes();
            $table->timestamps(3);

            $table->index('card_uid');
            $table->index('status');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
