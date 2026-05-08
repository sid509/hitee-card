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
        Schema::create('card_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['personalized', 'non-personalized']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('kyc_data')->nullable(); // For storing name, ID number, etc.
            $table->text('admin_remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('card_id')->nullable()->constrained()->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_applications');
    }
};
