<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->after('gram')->default(0); // 添加价格字段
            $table->date('delivery_date')->after('date')->nullable(); // 
            $table->decimal('discount', 5, 2)->default(0); 
        });
    }

    public function down()
    {
        Schema::table('manifests', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'delivery_date', 'discount']);
        });
    }
};
