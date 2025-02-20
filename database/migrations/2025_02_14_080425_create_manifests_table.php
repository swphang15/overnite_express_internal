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
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->string('origin');
            $table->foreignId('consignor_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('consignee_id')->constrained('agents')->onDelete('cascade');
            $table->integer('cn_no');
            $table->integer('pcs');
            $table->integer('kg');
            $table->integer('gram');
            $table->text('remarks')->nullable();
            $table->date('date');
            $table->integer('awb_no');
            $table->string('to');
            $table->string('from');
            $table->string('flt');
            $table->integer('manifest_no');
            
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest');
    }
};
