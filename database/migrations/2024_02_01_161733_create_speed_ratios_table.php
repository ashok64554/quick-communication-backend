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
        Schema::create('speed_ratios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->decimal('trans_text_sms', 5, 2);
            $table->decimal('promo_text_sms', 5, 2);
            $table->decimal('two_way_sms', 5, 2);
            $table->decimal('voice_sms', 5, 2);
            $table->decimal('whatsapp_sms', 5, 2);

            $table->decimal('trans_text_f_sms', 5, 2);
            $table->decimal('promo_text_f_sms', 5, 2);
            $table->decimal('two_way_f_sms', 5, 2);
            $table->decimal('voice_f_sms', 5, 2);
            $table->decimal('whatsapp_f_sms', 5, 2);

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
        Schema::dropIfExists('speed_ratios');
    }
};
