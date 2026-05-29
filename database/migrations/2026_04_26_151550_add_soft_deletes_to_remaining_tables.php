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
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('media', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('media', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
