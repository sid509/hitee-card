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
        Schema::table('balance_ins', function (Blueprint $table) {
            $table->foreignId('card_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
        });

        Schema::table('balance_outs', function (Blueprint $table) {
            $table->foreignId('card_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_ins', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->dropColumn('card_id');
        });

        Schema::table('balance_outs', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->dropColumn('card_id');
        });
    }
};
