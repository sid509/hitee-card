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
        Schema::create('merchant_route', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Migrate existing data from routes table to pivot table
        $routes = DB::table('routes')->select('id', 'merchant_id')->get();
        foreach ($routes as $route) {
            DB::table('merchant_route')->insert([
                'merchant_id' => $route->merchant_id,
                'route_id' => $route->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove the single merchant_id from routes
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropColumn('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->constrained('users')->onDelete('cascade');
        });

        // Re-populate merchant_id (using the first one found in pivot)
        $pivotData = DB::table('merchant_route')->select('route_id', 'merchant_id')->get()->groupBy('route_id');
        foreach ($pivotData as $routeId => $merchants) {
            DB::table('routes')->where('id', $routeId)->update(['merchant_id' => $merchants[0]->merchant_id]);
        }

        Schema::dropIfExists('merchant_route');
    }
};
