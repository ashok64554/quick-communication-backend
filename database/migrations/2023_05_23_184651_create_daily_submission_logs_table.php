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
        Schema::create('daily_submission_logs', function (Blueprint $table) {
            $table->id();
            $table->date('submission_date');
            $table->string('sms_gateway')->nullable();
            $table->string('submission')->nullable();
            $table->string('submission_credit_used')->nullable();
            $table->string('auto_submission')->nullable();
            $table->string('auto_submission_credit')->nullable();
            $table->string('overall_delivered')->nullable();
            $table->string('overall_delivered_credit')->nullable();
            $table->string('actual_delivered')->nullable();
            $table->string('actual_delivered_credit')->nullable();
            $table->string('other_than_delivered')->nullable();
            $table->string('other_than_delivered_credit')->nullable();
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
        Schema::dropIfExists('daily_submission_logs');
    }
};
