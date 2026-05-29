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
        Schema::table('taps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bus_id');
            
            $table->foreignId('merchant_id')->nullable()->after('card_id')->constrained('users')->onDelete('set null');
            $table->morphs('reference'); // bus_id or parking_id
            $table->unsignedBigInteger('stop_id')->nullable()->after('type'); // Resolved stop/station ID
            $table->string('resolved_location_name')->nullable()->after('stop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taps', function (Blueprint $table) {
            $table->dropMorphs('reference');
            $table->dropColumn(['merchant_id', 'stop_id', 'resolved_location_name']);
            $table->foreignId('bus_id')->after('card_id')->constrained()->onDelete('cascade');
        });
    }
};
