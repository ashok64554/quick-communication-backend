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
        Schema::create('send_sms', function (Blueprint $table) {
            $table->id();

            $table->string('uuid', 36)->comment('unique ID for response');
            
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('user_id');

            $table->string('campaign')->nullable();
            $table->integer('secondary_route_id');
            $table->bigInteger('dlt_template_id')->comment('DLT TEMPLATE ID (Mandatory for Indian Numbers)')->nullable();
            $table->integer('dlt_template_group_id')->nullable();
            $table->string('sender_id')->comment('6 char');

            
            $table->integer('route_type')->default(1)->comment('1:Transactional, 2:Promotional, 3:TwoWay, 4:Voice');
            $table->integer('country_id')->default(99);
            
            $table->enum('sms_type',[1,2])->default('normalsms')->comment('1:normalsms, 2:customsms')->default(1);
            $table->text('message');
            $table->enum('message_type',[1,2])->comment('1:English,2:Unicode');
            $table->boolean('is_flash')->default(0);

            $table->string('file_path')->nullable()->comment('if file upload');
            $table->string('file_mobile_field_name')->nullable()->comment('if file upload then enter csv mobile number column');
            $table->boolean('is_read_file_path')->default(0);
            
            $table->timestamp('campaign_send_date_time')->comment('for both type of campaign, schedule or instant')->nullable();
            $table->boolean('is_campaign_scheduled')->default(0)->nullable();

            $table->enum('priority',[0,1,2,3])->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');

            $table->integer('message_count')->default(0);
            $table->integer('message_credit_size');
            $table->integer('total_contacts')->default(0);
            $table->integer('total_block_number')->default(0)->comment('credit not deducted');
            $table->integer('total_invalid_number')->default(0)->comment('credit deducted but after finish campaign refund this');
            $table->integer('total_credit_deduct');
            $table->decimal('ratio_percent_set', 4,2)->default('0.00');
            $table->decimal('failed_ratio', 4,2)->nullable();
            
            $table->integer('total_delivered')->default(0);
            $table->integer('total_failed')->default(0);
            
            $table->boolean('is_credit_back')->default(0);
            $table->integer('self_credit_back')->nullable();
            $table->integer('parent_credit_back')->nullable();
            $table->timestamp('credit_back_date')->nullable();
            $table->boolean('is_update_auto_status')->default(0);

            $table->enum('status', ['Pending','In-process','Ready-to-complete','Completed','Stop'])->default('Pending');

            $table->bigInteger('reschedule_send_sms_id')->nullable()->comment('if reschedule campaign then send sms table id');
            $table->string('reschedule_type')->nullable()->comment('ALL,Pending,FAILED,Accepted, DELIVRD');

            $table->index('parent_id');
            $table->index('user_id');
            $table->index(
                ['campaign_send_date_time', 'user_id', 'sender_id'],
                'idx_sms_campaign_user_sender'
            );
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
        Schema::dropIfExists('send_sms');
    }
};
