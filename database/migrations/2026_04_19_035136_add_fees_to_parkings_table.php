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
        Schema::table('parkings', function (Blueprint $table) {
            $table->decimal('first_hour_fee', 10, 2)->default(0)->after('location');
            $table->decimal('onwards_hour_fee', 10, 2)->default(0)->after('first_hour_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parkings', function (Blueprint $table) {
            $table->dropColumn(['first_hour_fee', 'onwards_hour_fee']);
        });
    }
};
