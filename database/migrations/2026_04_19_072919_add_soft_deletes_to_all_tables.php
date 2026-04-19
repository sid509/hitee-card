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
        $tables = [
            'users', 'roles', 'permissions', 'buses', 'parkings', 'cards', 
            'support_requests', 'routes', 'route_stops', 'fares', 'fare_matrices', 
            'balance_ins', 'balance_outs', 'merchant_incomes', 'merchant_withdrawals', 
            'activity_logs', 'parking_attributes', 'taps', 'rides'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users', 'roles', 'permissions', 'buses', 'parkings', 'cards', 
            'support_requests', 'routes', 'route_stops', 'fares', 'fare_matrices', 
            'balance_ins', 'balance_outs', 'merchant_incomes', 'merchant_withdrawals', 
            'activity_logs', 'parking_attributes', 'taps', 'rides'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
