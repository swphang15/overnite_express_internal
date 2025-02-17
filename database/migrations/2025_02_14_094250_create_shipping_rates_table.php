<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('shipping_rates', function (Blueprint $table) {
        $table->id();
        $table->string('origin');
        $table->string('destination');
        $table->decimal('minimum_price', 8, 2);
        $table->integer('minimum_weight');
        $table->decimal('additional_price_per_kg', 8, 2);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
