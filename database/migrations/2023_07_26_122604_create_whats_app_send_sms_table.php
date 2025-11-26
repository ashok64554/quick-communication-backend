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
        Schema::create('whats_app_send_sms', function (Blueprint $table) {
            $table->id();

            $table->string('uuid', 36)->comment('unique ID for response');

            $table->unsignedBigInteger('user_id');

            $table->bigInteger('whats_app_configuration_id');
            $table->bigInteger('whats_app_template_id');
            $table->bigInteger('country_id');

            $table->string('campaign')->nullable();
            $table->string('sender_number', 30);
            $table->text('message')->nullable();
            $table->string('file_path')->nullable()->comment('if file upload');
            $table->string('file_mobile_field_name')->nullable()->comment('if file upload then enter csv mobile number column');
            $table->boolean('is_read_file_path')->default(0);
            
            $table->timestamp('campaign_send_date_time')->comment('for both type of campaign, schedule or instant')->nullable();
            $table->boolean('is_campaign_scheduled')->default(0)->nullable();
            $table->string('message_category', 20)->nullable();
            $table->decimal('charges_per_msg', 15,4);
            $table->integer('total_contacts')->default(0);
            $table->integer('total_block_number')->default(0)->comment('credit not deducted');
            $table->integer('total_invalid_number')->default(0)->comment('credit deducted but after finish campaign refund this');
            $table->decimal('total_credit_deduct', 15,4);
            $table->decimal('ratio_percent_set', 4,2)->default('0.00');
            $table->decimal('failed_ratio', 4,2)->nullable();
            
            $table->integer('total_sent')->default(0);
            $table->integer('total_delivered')->default(0);
            $table->integer('total_read')->default(0);
            $table->integer('total_failed')->default(0);
            $table->integer('total_other')->default(0);
            
            $table->boolean('is_credit_back')->default(0);
            $table->integer('self_credit_back')->nullable();
            $table->integer('parent_credit_back')->nullable();
            $table->timestamp('credit_back_date')->nullable();
            $table->boolean('is_update_auto_status')->default(0);

            $table->enum('status', ['Pending','In-process','Ready-to-complete','Completed','Stop'])->default('Pending');

            $table->bigInteger('reschedule_whats_app_send_sms_id')->nullable()->comment('if reschedule campaign then send whats app send sms table id');
            $table->string('reschedule_type')->nullable()->comment('ALL,Pending,FAILED,Accepted, DELIVRD');

            $table->index('user_id');
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
        Schema::dropIfExists('whats_app_send_sms');
    }
};
