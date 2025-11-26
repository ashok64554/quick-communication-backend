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
        Schema::create('whats_app_batches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('whats_app_send_sms_id');
            $table->foreign('whats_app_send_sms_id')->references('id')->on('whats_app_send_sms')->onDelete('cascade');

            $table->string('batch')->comment('batch_id, when WA campaign created, this ll help to push messages');
            $table->enum('current_status', ['1','2'])->default('1')->comment('1:Pending, 2:Processing');
            $table->tinyInteger('priority')->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');
            
            $table->timestamp('execute_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whats_app_batches');
    }
};
