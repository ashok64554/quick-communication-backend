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
        Schema::create('whats_app_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('country_name');
            $table->string('currency', 20)->default('₹');
            $table->decimal('marketing_charge', 10,4)->nullable();
            $table->decimal('utility_charge', 10,4)->nullable();
            $table->decimal('authentication_charge', 10,4)->nullable();
            $table->decimal('authentication_international_charge', 10,4)->nullable();
            $table->decimal('service_charge', 10,4)->nullable();
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
        Schema::dropIfExists('whats_app_rate_cards');
    }
};
