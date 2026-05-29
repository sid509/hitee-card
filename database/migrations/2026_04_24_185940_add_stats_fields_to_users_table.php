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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('total_balance', 15, 2)->default(0)->after('status');
            $table->integer('total_trips')->default(0)->after('total_balance');
            $table->integer('total_parking')->default(0)->after('total_trips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_balance', 'total_trips', 'total_parking']);
        });
    }
};
