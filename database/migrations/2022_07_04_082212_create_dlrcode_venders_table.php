<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dlrcode_venders', function (Blueprint $table) {
            $table->id();
            $table->integer('primary_route_id');
            $table->string('dlr_code');
            $table->text('description');
            $table->boolean('is_refund_applicable');
            $table->boolean('is_retry_applicable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dlrcode_venders');
    }
};
