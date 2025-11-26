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
        Schema::create('whats_app_chat_bot_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wa_chat_bot_id')->nullable();

            $table->unsignedBigInteger('whats_app_configuration_id')->nullable();

            $table->enum('flow_type', ['chatbot', 'template'])->default('chatbot')->comment('Either Chatbot or Template Id, depending on customer reply');
            $table->string('customer_number', 25);
            $table->string('current_step')->comment('here we can set current step id or current step name');
            $table->integer('loop_count')->default(0)->nullable()->comment('here we can set same step count to prevent infinite live');
            $table->json('meta')->nullable();

            $table->enum('status', ['active','completed','expired'])->default('active');
            $table->json('context_vars')->nullable(); // store PNR, traits, answers
            $table->text('last_message')->nullable();
            $table->boolean('ended')->default(false);
            $table->timestamp('last_activity_at')->nullable();

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
        Schema::dropIfExists('whats_app_chat_bot_sessions');
    }
};
