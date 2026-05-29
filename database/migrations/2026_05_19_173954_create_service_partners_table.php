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
        if (!Schema::hasTable('service_partners')) {
            Schema::create('service_partners', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
                $blueprint->string('name');
                $blueprint->string('service_type')->default('restaurant');
                $blueprint->string('address')->nullable();
                $blueprint->decimal('latitude', 10, 8)->nullable();
                $blueprint->decimal('longitude', 11, 8)->nullable();
                $blueprint->string('status')->default('active');
                $blueprint->softDeletes();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_partners');
    }
};
