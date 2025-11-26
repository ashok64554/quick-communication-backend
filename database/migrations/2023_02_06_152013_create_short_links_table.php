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
        if (!Schema::connection('mysql_twoway')->hasTable('short_links')) 
        {
            Schema::connection('mysql_twoway')->create('short_links', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('parent_id')->comment('comes from user table');
                
                $table->unsignedBigInteger('two_way_comm_id');
                $table->foreign('two_way_comm_id')->references('id')->on('two_way_comms')->onDelete('cascade');

                $table->unsignedBigInteger('send_sms_id');

                $table->string('code')->comment('domain.name/{sub_part}/{token}');
                $table->string('sub_part', 1)->comment('random single characters:[a-z,0-9]');
                $table->string('token', 5)->comment('random 5 characters:[a-z,0-9]');
                $table->string('mobile_num');
                $table->string('link')->comment('domain.name/{uuid}/{sub_part}/{token}/{mobile}');
                
                $table->integer('total_click')->default('0');
                $table->date('link_expired')->nullable();
                
                $table->index('two_way_comm_id');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('mysql_twoway')->dropIfExists('short_links');
    }
};
