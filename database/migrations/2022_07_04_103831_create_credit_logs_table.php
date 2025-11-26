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
        Schema::create('credit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('created_by')->nullable()->comment('used when credit add by any user');

            $table->enum('log_type', [1,2])->nullable()->comment('1:API,2:Campign');
            $table->enum('action_for',[1,2,3,4,5])->default(1)->comment('1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms, 5:whatsapp');
            $table->enum('credit_type', [1,2])->default(1)->comment('1:credit, 2:debit');
            $table->decimal('old_balance', 15, 4)->default(0);
            $table->decimal('balance_difference', 15, 4)->default(0)->comment('actual number of add/minus value');
            $table->decimal('current_balance', 15, 4)->default(0);
            $table->decimal('rate', 10, 2)->nullable()->comment('only used when creditted');
            $table->string('comment')->nullable();
            $table->string('scurrbing_sms_adjustment')->nullable()->comment('Scurrbing SMS adjust according to SMS rate and scurrbing charges.');
            
            $table->index('user_id');
            $table->index('created_by');
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
        Schema::dropIfExists('credit_logs');
    }
};
