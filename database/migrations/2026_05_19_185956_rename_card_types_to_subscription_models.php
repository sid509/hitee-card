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
        if (!Schema::hasTable('subscription_models')) {
            Schema::create('subscription_models', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->enum('category', ['transit', 'dine_in']);
                $blueprint->decimal('price', 10, 2)->default(0.00);
                $blueprint->boolean('is_active')->default(true);
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
        Schema::dropIfExists('subscription_models');
    }
};
