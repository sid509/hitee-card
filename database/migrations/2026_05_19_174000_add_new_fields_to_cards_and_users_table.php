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
        Schema::table('users', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('users', 'kyc_status')) {
                $blueprint->string('kyc_status')->default('pending')->after('status');
            }
            if (!Schema::hasColumn('users', 'merchant_type')) {
                $blueprint->string('merchant_type')->nullable()->after('kyc_status');
            }
        });

        if (!Schema::hasTable('card_subscription_model')) {
            Schema::create('card_subscription_model', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->foreignUuid('card_id')->constrained()->onDelete('cascade');
                $blueprint->foreignId('subscription_model_id')->constrained('subscription_models')->onDelete('cascade');
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['kyc_status', 'merchant_type']);
        });

        Schema::dropIfExists('card_subscription_model');
    }
};
