<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('manifest_infos', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('awb_no')->unique();
            $table->string('to');
            $table->string('from');
            $table->string('flt')->nullable();
            $table->string('manifest_no')->unique();
            $table->timestamps();
            $table->softDeletes(); // 软删除字段 deleted_at
            
        });
    }

    public function down()
    {
        Schema::dropIfExists('manifest_infos');
    }
};
