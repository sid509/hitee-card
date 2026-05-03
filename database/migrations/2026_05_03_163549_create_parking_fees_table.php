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
        Schema::create('parking_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->decimal('price_rs', 10, 2);
            $table->decimal('price_pts', 10, 2);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // We will keep the old columns for now to prevent breakage during the transition
        // They will be removed in a future migration once all logic is updated
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_fees');
    }
};
