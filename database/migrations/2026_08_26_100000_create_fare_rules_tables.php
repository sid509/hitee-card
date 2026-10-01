<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fare_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_code', 32)->unique('uq_fare_rule_code');
            $table->string('description', 500)->nullable();
            $table->string('route_id', 64)->nullable();
            $table->string('fare_type', 24)->default('DISTANCE'); // DISTANCE, FLAT, ZONED
            $table->integer('base_fare_minor_units')->default(0);
            $table->integer('rate_per_km_minor_units')->default(0);
            $table->integer('min_fare_minor_units')->default(0);
            $table->integer('max_fare_minor_units')->default(0);
            $table->double('max_distance_km')->default(80.0);
            $table->integer('flat_fare_minor_units')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps(3);

            $table->index(['route_id', 'is_active']);
            $table->index('effective_from');
        });

        Schema::create('fare_rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fare_rule_id');
            $table->foreign('fare_rule_id')->references('id')->on('fare_rules')->onDelete('cascade');
            $table->integer('version');
            $table->json('snapshot');
            $table->string('changed_by', 128)->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['fare_rule_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fare_rule_versions');
        Schema::dropIfExists('fare_rules');
    }
};
