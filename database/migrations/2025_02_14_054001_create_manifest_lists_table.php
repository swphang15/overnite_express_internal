<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('manifest_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_info_id')->constrained('manifest_infos')->onDelete('cascade');
            $table->foreignId('consignor_id')->constrained('clients')->onDelete('cascade');
            $table->string('consignee_name');
            $table->string('cn_no');
            $table->integer('pcs');
            $table->integer('kg');
            $table->integer('gram');
            $table->text('remarks')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();
            $table->string('origin');
            $table->string('destination');
            $table->decimal('fuel_surcharge', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes(); // 软删除字段 deleted_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('manifest_lists');
    }
};
