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
        Schema::create('callback_webhooks', function (Blueprint $table) {
            $table->id();
            
            $table->tinyInteger('message_type')->default('1')->comment('1:text, 2:voice, 3:whatsapp');
            $table->text('webhook_url', 500)->nullable();
            $table->text('response');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('callback_webhooks');
    }
};
