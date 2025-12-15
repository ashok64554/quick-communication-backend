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
        Schema::create('send_sms_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('send_sms_id');
            $table->foreign('send_sms_id')->references('id')->on('send_sms')->onDelete('cascade');


            $table->integer('primary_route_id')->nullable()->comment('sms send throught this primary route');
            $table->string('unique_key');
            $table->string('mobile');
            $table->text('message');
            $table->integer('use_credit')->default(1);
            $table->boolean('is_auto')->default(0)->comment('0:default,1:D,2:F');
            $table->string('stat',50)->default('Pending');
            $table->string('err',5)->nullable();
            $table->enum('status', ['Pending','Process','Completed','Stop'])->default('Pending');
            
            $table->string('submit_date',50)->nullable();
            $table->string('done_date',50)->nullable();
            $table->string('response_token',70)->nullable();
            $table->string('sub',5)->nullable();
            $table->string('dlvrd',5)->nullable();
            
            $table->index('send_sms_id');
            $table->index('unique_key');
            $table->index(
                ['send_sms_id', 'err', 'stat'],
                'idx_ssh_send_sms_err_stat'
            );
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
        Schema::dropIfExists('send_sms_histories');
    }
};
