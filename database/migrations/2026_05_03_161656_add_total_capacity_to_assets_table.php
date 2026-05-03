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
        Schema::table('buses', function (Blueprint $table) {
            $table->unsignedInteger('total_capacity')->default(0)->after('bus_number');
        });

        Schema::table('parkings', function (Blueprint $table) {
            $table->unsignedInteger('total_capacity')->default(0)->after('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn('total_capacity');
        });

        Schema::table('parkings', function (Blueprint $table) {
            $table->dropColumn('total_capacity');
        });
    }
};
