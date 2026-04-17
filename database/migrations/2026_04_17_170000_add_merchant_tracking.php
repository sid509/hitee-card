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
        Schema::table('balance_outs', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('user_id')->constrained('users');
        });

        Schema::create('merchant_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('balance_out_id')->constrained('balance_outs')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('type'); // fare, parking
            $table->timestamps();
        });

        Schema::create('merchant_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->string('transaction_id')->nullable();
            $table->string('gateway_name')->default('khalti');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_withdrawals');
        Schema::dropIfExists('merchant_incomes');
        Schema::table('balance_outs', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropColumn('merchant_id');
        });
    }
};
