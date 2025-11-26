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
        Schema::create('campaign_executers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('send_sms_id');
            $table->foreign('send_sms_id')->references('id')->on('send_sms')->onDelete('cascade');

            $table->timestamp('campaign_send_date_time');
            $table->integer('campaign_type')->comment('1:Transactional, 2:Promotional, 3:TwoWay, 4:Voice');
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
        Schema::dropIfExists('campaign_executers');
    }
};
