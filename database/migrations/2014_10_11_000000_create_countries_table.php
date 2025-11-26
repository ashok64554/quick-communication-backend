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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->string('name')->comment('Like India, United States');
            $table->string('iso', 10)->comment('Like IN, US');
            $table->string('iso3', 10)->nullable()->comment('Like IND, USA');
            $table->string('currency', 50)->comment('Like Indian rupee, United States dollar');
            $table->string('currency_code', 10)->nullable()->comment('Like INR, USD');
            $table->string('currency_symbol', 20)->comment('Like ₹, $');
            $table->string('phonecode', 20)->comment('Like 91 for India');
            $table->string('min', 4)->nullable()->comment('Like minimum length of Indian phone number is 10');
            $table->string('max', 4)->nullable()->comment('Like minimum length of Indian phone number is 12');
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
        Schema::dropIfExists('countries');
    }
};
