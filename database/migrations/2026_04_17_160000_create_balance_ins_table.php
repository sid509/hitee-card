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
        Schema::create('balance_ins', function (Blueprint $user) {
            $user->id();
            $user->foreignId('user_id')->constrained()->onDelete('cascade');
            $user->decimal('amount', 10, 2);
            $user->string('type'); // manual, cashback, khalti, etc.
            $user->string('remarks')->nullable();
            $user->foreignId('created_by')->nullable()->constrained('users');
            $user->string('gateway_name')->nullable();
            $user->string('transaction_id')->nullable();
            $user->string('status')->default('completed'); // pending, completed, failed
            $user->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_ins');
    }
};
