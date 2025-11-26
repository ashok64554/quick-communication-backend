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
        Schema::create('manage_sender_ids', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parent_id');
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->comment('sender id assigned to this user');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('company_name')->nullable();
            $table->string('entity_id');
            $table->string('header_id');
            $table->string('sender_id');
            $table->integer('sender_id_type')->default(1)->comment('1:Transactional, 2:Promotional');
            $table->enum('status', ['1','2','3'])->default('1')->comment('1:Pending, 2:Approved, 3:Rejected');
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
        Schema::dropIfExists('manage_sender_ids');
    }
};
