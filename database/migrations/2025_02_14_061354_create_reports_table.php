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
    Schema::create('reports', function (Blueprint $table) {
        $table->id();
        $table->string('origin');
        $table->string('consignor');
        $table->string('consignee');
        $table->string('cn_no');
        $table->integer('pcs');
        $table->integer('kg');
        $table->integer('gram');
        $table->text('remarks')->nullable();
        $table->date('date');
        $table->string('awb_no');
        $table->string('to');
        $table->string('from');
        $table->string('flt');
        $table->string('manifest_no');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
