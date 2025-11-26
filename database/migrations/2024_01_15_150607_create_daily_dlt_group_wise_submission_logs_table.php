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
        Schema::create('daily_dlt_group_wise_submission_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('submission_date');
            $table->string('dlt_template_group_id');
            $table->string('dlt_template_group_name');
            $table->bigInteger('total_submission')->default(0);
            $table->bigInteger('submission_credit_used')->default(0);
            $table->bigInteger('total_delivered')->default(0);
            $table->bigInteger('total_failed')->default(0);
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
        Schema::dropIfExists('daily_dlt_group_wise_submission_logs');
    }
};
