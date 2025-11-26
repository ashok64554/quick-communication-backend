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
        Schema::create('user_wise_monthly_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('group_name')->nullable();
            $table->string('month');
            $table->integer('year');
            $table->string('sms_count_submission');
            $table->string('sms_count_delivered');
            $table->string('sms_count_failed');
            $table->string('sms_count_rejected');
            $table->string('sms_count_invalid');
            $table->string('mobile_count_submission');
            $table->string('mobile_count_delivered');
            $table->string('mobile_count_failed');
            $table->string('mobile_count_rejected');
            $table->string('mobile_count_invalid');
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
        Schema::dropIfExists('user_wise_monthly_reports');
    }
};
