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
        Schema::table('rides', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('card_id')->constrained('users')->onDelete('set null');
            $table->morphs('reference'); // bus_id or parking_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropMorphs('reference');
            $table->dropColumn('merchant_id');
        });
    }
};
