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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->string('stop_name');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('status')->default('proposed'); // proposed, approved, rejected, archived
            $table->timestamp('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('fare_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fare_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_stop_id')->constrained('route_stops')->onDelete('cascade');
            $table->foreignId('to_stop_id')->constrained('route_stops')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('active_fare_id')->nullable()->constrained('fares')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropForeign(['active_fare_id']);
            $table->dropColumn(['route_id', 'active_fare_id']);
        });
        Schema::dropIfExists('fare_matrices');
        Schema::dropIfExists('fares');
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
