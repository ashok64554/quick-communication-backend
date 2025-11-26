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
        if (!Schema::connection('mysql_twoway')->hasTable('two_way_comms')) 
        {
            Schema::connection('mysql_twoway')->create('two_way_comms', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('parent_id')->comment('comes from user table');
                
                $table->unsignedBigInteger('created_by')->comment('this Twoway template belongs to this user');
                $table->enum('is_web_temp',[1,2])->default('1')->comment('1:web template, 2:other');
                $table->string('redirect_url')->nullable();
                $table->string('title')->nullable();
                $table->longText('content')->nullable();
                $table->string('bg_color')->nullable();
                $table->date('content_expired')->nullable();
                $table->integer('take_response')->nullable()->comment('null:NO, 1:Interested, 2:Feedback Form, 3: Rating');
                $table->string('response_mob_num')->nullable();
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
        Schema::connection('mysql_twoway')->dropIfExists('two_way_comms');
    }
};
