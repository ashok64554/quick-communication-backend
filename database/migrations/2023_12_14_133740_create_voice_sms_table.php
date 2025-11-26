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
        Schema::create('voice_sms', function (Blueprint $table) {
            $table->id();

            $table->string('uuid', 36)->comment('unique ID for response');
            
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('user_id');

            $table->string('campaign_id')->nullable()->comment('return from voice gateway');
            $table->string('transection_id')->nullable()->comment('return from voice gateway');
            $table->string('campaign')->nullable();
            $table->enum('obd_type', [1,2,3,4])->comment('1:SINGLE_VOICE, 2: DTMF (Dual Tone Multi Frequency), 3: CallPatch, 4:OTP (self created)');
            $table->string('dtmf', 2)->nullable()->comment('Dual Tone Multi Frequency any keyboard number for input as a request');
            $table->bigInteger('call_patch_number')->nullable()->comment('for direct calling');
            $table->integer('secondary_route_id');
            $table->bigInteger('voice_upload_id')->comment('comes from voice_uploads table (id column)')->nullable();
            $table->bigInteger('voice_id')->comment('comes from voice_uploads table (voice_id column)')->nullable();
            $table->string('voice_file_path')->comment('for future reference, audio file path');

            $table->integer('country_id')->default(99);

            $table->string('file_path')->nullable()->comment('if file upload');
            $table->string('file_mobile_field_name')->nullable()->comment('if file upload then enter csv mobile number column');
            $table->boolean('is_read_file_path')->default(0);
            
            $table->timestamp('campaign_send_date_time')->comment('for both type of campaign, schedule or instant')->nullable();
            $table->boolean('is_campaign_scheduled')->default(0)->nullable();

            $table->enum('priority',[0,1,2,3])->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');

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

            $table->enum('status', ['Pending','In-process','Ready-to-complete','Completed','Stop'])->default('Pending')->comment('Pending, In-process, Ready-to-complete, Completed, Stop');

            $table->index('parent_id');
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
        Schema::dropIfExists('voice_sms');
    }
};
