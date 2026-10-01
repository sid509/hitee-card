<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add rules_config column to settlement batches
        if (!Schema::hasColumn('cm_settlement_batches', 'rules_config')) {
            Schema::table('cm_settlement_batches', function (Blueprint $table) {
                $table->json('rules_config')->nullable()->after('commission_rate');
            });
        }

        Schema::create('cm_settlement_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 128);
            $table->json('rules_json');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('changed_by', 128)->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_settlement_rules');
        if (Schema::hasColumn('cm_settlement_batches', 'rules_config')) {
            Schema::table('cm_settlement_batches', function (Blueprint $table) {
                $table->dropColumn('rules_config');
            });
        }
    }
};
