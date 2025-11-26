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
        Schema::create('whats_app_chat_bots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('whats_app_configuration_id')->nullable();
            $table->foreign('whats_app_configuration_id')->references('id')->on('whats_app_configurations')->onDelete('cascade');

            $table->unsignedBigInteger('whats_app_template_id')->nullable();
            $table->foreign('whats_app_template_id')->references('id')->on('whats_app_templates')->onDelete('cascade');

            $table->string('display_phone_number_req');
            $table->string('chatbot_name');
            $table->enum('matching_criteria', ['exact','contain']);
            $table->string('start_with');
            $table->longText('automation_flow');
            $table->longText('request_payload');

            $table->index(['start_with', 'matching_criteria']);

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
        Schema::dropIfExists('whats_app_chat_bots');
    }
};
