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
        Schema::create('whats_app_configurations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('sender_number');
            $table->string('display_phone_number', 50)->nullable()->comment('Actual profile contact number');
            $table->string('display_phone_number_req', 50)->nullable()->comment('Actual profile contact number without + and space');
            $table->string('name')->nullable()->comment('OK go account name, just for info');
            $table->bigInteger('business_account_id')->nullable();
            $table->bigInteger('waba_id')->nullable();
            $table->bigInteger('app_id')->nullable();
            $table->string('app_version')->nullable();
            $table->text('access_token')->nullable()->comment('base64 enc data');
            $table->string('verified_name')->nullable();
            $table->string('code_verification_status')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('platform_type')->nullable();
            $table->timestamp('last_quality_checked')->nullable();
            $table->string('current_limit', 50)->nullable();

            $table->boolean('is_cart_enabled')->nullable()->default(false);
            $table->boolean('is_catalog_visible')->nullable()->default(false);
            $table->bigInteger('wa_commerce_setting_id')->nullable();

            $table->string('business_category')->nullable();
            $table->text('wa_business_page')->nullable();
            $table->string('messsage_limit')->nullable();
            $table->enum('wa_status', ['CONNECTED', 'DISCONNECTED'])->nullable()->comment('Like Connected/Disconnected');
            $table->enum('privacy_read_receipt', ['1','0'])->default('1')->nullable();
            $table->enum('privacy_deregister_mobile', ['1','0'])->default('0')->nullable();
            $table->enum('enable_auto_response', ['1','0'])->default('0')->nullable();
            $table->text('auto_response_message')->nullable();

            $table->text('calling_setting')->nullable()->comment('json object');

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
        Schema::dropIfExists('whats_app_configurations');
    }
};
