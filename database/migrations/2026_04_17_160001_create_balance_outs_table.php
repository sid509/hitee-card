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
        Schema::create('balance_outs', function (Blueprint $user) {
            $user->id();
            $user->foreignId('user_id')->constrained()->onDelete('cascade');
            $user->decimal('amount', 10, 2);
            $user->string('type'); // fare_deduction, penalty, etc.
            $user->string('remarks')->nullable();
            $user->unsignedBigInteger('reference_id')->nullable();
            $user->string('reference_type')->nullable(); // For polymorphism if needed
            $user->foreignId('created_by')->nullable()->constrained('users');
            $user->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_outs');
    }
};
