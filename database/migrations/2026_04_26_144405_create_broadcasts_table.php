<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('sent_by')->constrained('users');
            $table->enum('type', ['email', 'fcm','sms']);
            $table->string('title')->nullable(); // Brief description or custom subject
            $table->integer('total_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
