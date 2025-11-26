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
        if (!Schema::connection('mysql_twoway')->hasTable('two_way_comm_interests')) 
        {
            Schema::connection('mysql_twoway')->create('two_way_comm_interests', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('two_way_comm_id');
                $table->foreign('two_way_comm_id')->references('id')->on('two_way_comms')->onDelete('cascade');

                $table->unsignedBigInteger('short_link_id');
                $table->foreign('short_link_id')->references('id')->on('short_links')->onDelete('cascade');

                $table->unsignedBigInteger('send_sms_id');
                
                $table->string('mobile');
                $table->boolean('is_interest')->default(1);
                $table->string('ip');

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
        Schema::connection('mysql_twoway')->dropIfExists('two_way_comm_interests');
    }
};
