<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fare_merchant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fare_id')->constrained()->onDelete('cascade');
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // Migrate existing data from fares table to pivot table
        $fares = DB::table('fares')->select('id', 'merchant_id')->get();
        foreach ($fares as $fare) {
            DB::table('fare_merchant')->insert([
                'fare_id' => $fare->id,
                'merchant_id' => $fare->merchant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove the single merchant_id from fares
        Schema::table('fares', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropColumn('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fares', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->constrained('users')->onDelete('cascade');
        });

        // Re-populate merchant_id (using the first one found in pivot)
        $pivotData = DB::table('fare_merchant')->select('fare_id', 'merchant_id')->get()->groupBy('fare_id');
        foreach ($pivotData as $fareId => $merchants) {
            DB::table('fares')->where('id', $fareId)->update(['merchant_id' => $merchants[0]->merchant_id]);
        }

        Schema::dropIfExists('fare_merchant');
    }
};
