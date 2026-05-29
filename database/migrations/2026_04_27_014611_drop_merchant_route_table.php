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
        Schema::dropIfExists('merchant_route');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('merchant_route', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
};
