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
        Schema::create('whats_app_send_sms_queues', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('whats_app_send_sms_id');
            $table->foreign('whats_app_send_sms_id')->references('id')->on('whats_app_send_sms')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('batch_id')->nullable()->comment('batch for execute queue');
            $table->string('unique_key');
            $table->string('sender_number');
            $table->string('mobile');
            $table->string('template_category')->nullable();
            $table->text('message');
            $table->decimal('use_credit', 15,4)->nullable();
            $table->boolean('is_auto')->default(0)->comment('0:default,1:D,2:F');
            $table->string('stat')->default('Pending')->comment('Pending, INVALID, sent, read etc.');

            $table->enum('status', ['Pending','Process','Completed','Stop'])->default('Pending');
            $table->text('error_info')->nullable()->comment('Any error or info');
            $table->string('submit_date',50)->nullable();
            
            $table->string('response_token',70)->nullable();
            $table->string('conversation_id', 50)->nullable();
            $table->timestamp('expiration_timestamp')->nullable();
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_date_time')->nullable();
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_date_time')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_date_time')->nullable();

            $table->boolean('meta_billable')->nullable();
            $table->string('meta_pricing_model', 50)->nullable();
            $table->string('meta_billing_category', 50)->nullable();
            
            $table->index('whats_app_send_sms_id');
            $table->index('response_token');
            $table->index('conversation_id');
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
        Schema::dropIfExists('whats_app_send_sms_queues');
    }
};
