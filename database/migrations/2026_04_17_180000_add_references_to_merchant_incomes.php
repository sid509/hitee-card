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
        Schema::table('merchant_incomes', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')->nullable()->after('balance_out_id');
            $table->string('reference_type')->nullable()->after('reference_id'); // bus, parking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_incomes', function (Blueprint $table) {
            $table->dropColumn(['reference_id', 'reference_type']);
        });
    }
};
