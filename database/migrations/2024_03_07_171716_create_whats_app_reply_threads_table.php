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
        Schema::create('whats_app_reply_threads', function (Blueprint $table) {
            $table->id();

            $table->string('queue_history_unique_key', 50)->nullable();

            $table->unsignedBigInteger('whats_app_send_sms_id')->nullable();
            $table->foreign('whats_app_send_sms_id')->references('id')->on('whats_app_send_sms')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('profile_name')->nullable();
            $table->string('phone_number_id')->comment('whats_app_configurations table sender_number');
            $table->string('display_phone_number')->comment('');
            $table->string('user_mobile')->nullable();
            $table->string('message_type')->nullable();
            $table->text('message')->nullable();
            $table->text('json_message')->nullable();
            $table->string('media_id', 50)->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->text('media_url')->nullable();
            $table->string('context_ref_wa_id')->nullable();
            $table->text('error_info')->nullable();
            $table->string('received_date')->nullable();
            $table->string('response_token')->nullable();
            $table->decimal('use_credit', 15, 4)->nullable();
            $table->boolean('is_vendor_reply')->nullable()->default(0);
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
        Schema::dropIfExists('whats_app_reply_threads');
    }
};
