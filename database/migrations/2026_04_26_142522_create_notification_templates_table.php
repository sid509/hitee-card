<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'fcm','sms'])->default('fcm');
            $table->string('name');
            $table->string('subject_en')->nullable(); // Optional for FCM
            $table->string('subject_ne')->nullable(); // Optional for FCM
            $table->text('body_en');
            $table->text('body_ne');
            $table->json('variables')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
