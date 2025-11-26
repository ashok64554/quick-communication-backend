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
        Schema::create('voice_sms_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('voice_sms_id');
            $table->foreign('voice_sms_id')->references('id')->on('voice_sms')->onDelete('cascade');

            $table->integer('primary_route_id')->nullable()->comment('sms send throught this primary route');
            $table->string('unique_key');
            $table->string('mobile');
            $table->string('voice_id');
            $table->integer('use_credit')->default(1);
            $table->boolean('is_auto')->default(0)->comment('0:default,1:D,2:F');
            $table->string('stat',50)->default('Pending')->comment('Answered');
            $table->string('err')->nullable();
            $table->string('submit_date',50)->nullable();
            $table->string('done_date',50)->nullable();
            $table->enum('status', ['Pending','Process','Completed','Stop'])->default('Pending');

            $table->string('response_token',70)->nullable();
            $table->string('cli',50)->nullable();
            $table->string('flag',5)->nullable();
            $table->string('start_time',50)->nullable();
            $table->string('end_time',50)->nullable();
            $table->integer('duration')->nullable();
            $table->string('dtmf', 10)->nullable();
            
            $table->index('voice_sms_id');
            $table->index('unique_key');
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
        Schema::dropIfExists('voice_sms_histories');
    }
};
