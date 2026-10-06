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
            $table->unsignedBigInteger('external_id')->nullable()->after('id')->index();
            $table->string('owner_name')->nullable()->after('merchant_id');
            $table->string('owner_phone')->nullable()->after('owner_name');
            $table->text('notes')->nullable()->after('owner_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parkings', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'owner_name', 'owner_phone', 'notes']);
        });
    }
};
