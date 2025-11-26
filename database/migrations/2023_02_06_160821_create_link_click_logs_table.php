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
        if (!Schema::connection('mysql_twoway')->hasTable('link_click_logs')) 
        {
            Schema::connection('mysql_twoway')->create('link_click_logs', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('two_way_comm_id');
                $table->foreign('two_way_comm_id')->references('id')->on('two_way_comms')->onDelete('cascade');

                $table->unsignedBigInteger('short_link_id');
                $table->foreign('short_link_id')->references('id')->on('short_links')->onDelete('cascade');

                $table->unsignedBigInteger('send_sms_id');

                $table->string('mobile');
                $table->string('ip');
                $table->string('browserName')->nullable();
                $table->string('browserFamily')->nullable();
                $table->string('browserVersion')->nullable();
                $table->string('browserEngine')->nullable();
                $table->string('platformName')->nullable();
                $table->string('deviceFamily')->nullable();
                $table->string('deviceModel')->nullable();
                
                $table->index('two_way_comm_id');
                $table->index('short_link_id');
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
        Schema::connection('mysql_twoway')->dropIfExists('link_click_logs');
    }
};
