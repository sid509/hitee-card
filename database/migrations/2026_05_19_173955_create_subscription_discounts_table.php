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
        if (!Schema::hasTable('subscription_discounts')) {
            Schema::create('subscription_discounts', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->foreignId('subscription_model_id')->nullable()->constrained('subscription_models')->onDelete('cascade');
                $blueprint->foreignId('service_partner_id')->constrained('service_partners')->onDelete('cascade');
                $blueprint->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
                $blueprint->decimal('discount_value', 10, 2);
                $blueprint->decimal('min_spend', 10, 2)->default(0.00);
                $blueprint->string('description')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
