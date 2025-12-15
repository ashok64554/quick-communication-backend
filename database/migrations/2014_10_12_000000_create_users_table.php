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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->integer('userType')->default(2)->comment('0:Admin,1:reseller,2:client,3:employee');
            $table->enum('is_belongs_to_admin',[1,2])->default(null)->nullable()->comment('1:yes, 2:no');

            $table->integer('parent_id')->nullable()->comment(' reseller Id');
            $table->integer('current_parent_id')->nullable()->comment(' reseller Id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('username',50)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('mobile');
            $table->string('address');
            $table->integer('country')->default(99);
            $table->string('city')->nullable();
            $table->string('zipCode')->nullable();
            $table->string('companyName')->nullable();
            $table->string('companyLogo')->default('logo.png')->nullable();
            $table->string('websiteUrl')->nullable();
            $table->string('app_key')->unique();
            $table->string('app_secret');
            $table->string('designation')->nullable();
            $table->boolean('is_show_ratio')->default(false);
            $table->enum('authority_type', [1,2])->default(1)->comment('1:onDelivered, 2:onSubmission');
            $table->boolean('is_enabled_api_ip_security')->default(false);
            $table->integer('is_visible_dlt_template_group')->default(2)->comment('1:Yes, 2:No');
            $table->string('locktimeout')->default('30')->comment('System auto logout if no activity found. value in minutes');
            $table->enum('status', ['0','1','2','3'])->default('1')->comment('0:Inactive, 1:Active, 2:Delete, 3:Inactive form superadmin through reseller inactive status');


            //routes and credits
            $table->integer('otp_route')->nullable()->comment('secondary route id')->comment('transactional credit used.');
            $table->integer('promotional_route')->nullable()->comment('secondary route id');
            $table->integer('promotional_credit')->default(0);
            $table->integer('transaction_route')->nullable()->comment('secondary route id');
            $table->integer('transaction_credit')->default(0);
            $table->integer('two_waysms_route')->nullable()->comment('secondary route id');
            $table->integer('two_waysms_credit')->default(0);
            $table->integer('voice_sms_route')->nullable()->comment('secondary route id');
            $table->integer('voice_sms_credit')->default(0);
            $table->decimal('whatsapp_credit', 15,4)->default(0);
            $table->integer('account_type')->default(1)->comment('1:prepaid, 2:postpaid');


            $table->boolean('allow_to_add_webhook')->nullable()->default(false);
            $table->text('webhook_callback_url', 500)->nullable();
            $table->string('webhook_signing_key')->nullable();

            $table->boolean('allow_detail_report')->nullable()->default(false);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
