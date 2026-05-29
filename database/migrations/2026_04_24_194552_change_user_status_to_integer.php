<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing status strings to integers
        DB::table('users')->where('status', 'active')->update(['status' => '1']);
        DB::table('users')->where('status', 'inactive')->update(['status' => '0']);
        DB::table('users')->whereNotIn('status', ['1', '0'])->update(['status' => '-1']);

        Schema::table('users', function (Blueprint $table) {
            $table->integer('status')->default(-1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }
};
