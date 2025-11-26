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
        Schema::create('whats_app_qr_codes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('whats_app_configuration_id')->nullable();
            $table->foreign('whats_app_configuration_id')->references('id')->on('whats_app_configurations')->onDelete('cascade');

            $table->string('qr_image_format')->nullable();
            $table->string('prefilled_message');
            $table->string('code')->nullable();
            $table->string('deep_link_url')->nullable();
            $table->text('qr_image_url')->nullable();

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
        Schema::dropIfExists('whats_app_qr_codes');
    }
};
