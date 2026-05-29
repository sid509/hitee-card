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

        Schema::table('cards', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('cards', 'is_currently_active')) {
                $blueprint->boolean('is_currently_active')->default(false)->after('status');
            }
            if (!Schema::hasColumn('cards', 'is_physical')) {
                $blueprint->boolean('is_physical')->default(true)->after('is_currently_active');
            }
            if (!Schema::hasColumn('cards', 'is_personalized')) {
                $blueprint->boolean('is_personalized')->default(false)->after('is_physical');
            }
        });
        
        if (!Schema::hasTable('card_subscription_model')) {
            Schema::create('card_subscription_model', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->foreignId('card_id')->constrained()->onDelete('cascade');
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

        Schema::table('cards', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['is_currently_active', 'is_physical', 'is_personalized']);
        });

        Schema::dropIfExists('card_subscription_model');
    }
};
