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
        Schema::create('whats_app_template_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whats_app_template_id');
            $table->foreign('whats_app_template_id')->references('id')->on('whats_app_templates')->onDelete('cascade');
            
            $table->enum('button_type',['QUICK_REPLY','PHONE_NUMBER', 'URL','CATALOG','MPM','VOICE_CALL','COPY_CODE']);
            $table->string('url_type')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_val_name')->nullable();
            $table->string('button_value')->nullable();
            $table->string('button_variables')->nullable();
            $table->string('flow_id')->nullable();
            $table->string('flow_action')->nullable();
            $table->string('navigate_screen')->nullable();
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
        Schema::dropIfExists('whats_app_template_buttons');
    }
};
