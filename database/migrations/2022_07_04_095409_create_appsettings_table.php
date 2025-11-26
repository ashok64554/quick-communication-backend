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
        Schema::create('appsettings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->nullable();
            $table->string('app_logo')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('contact_number')->nullable();
            $table->decimal('tax_percentage', 4, 2)->default('18.00');
            $table->integer('file_gen_if_exceed')->default(5000)->nullable();
            $table->integer('order_no_start')->default(10001)->nullable();
            $table->string('g_key', 50)->nullable();
            $table->string('g_secret', 50)->nullable();
            $table->longText('privacy_policy')->nullable();
            $table->longText('terms_and_conditions')->nullable();
            $table->longText('cookies_protection')->nullable();
            $table->longText('cookies_disclaimer')->nullable();
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
        Schema::dropIfExists('appsettings');
    }
};
