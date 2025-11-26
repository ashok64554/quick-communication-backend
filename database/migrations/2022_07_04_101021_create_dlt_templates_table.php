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
        Schema::create('dlt_templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parent_id');
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('manage_sender_id');
            $table->foreign('manage_sender_id')->references('id')->on('manage_sender_ids')->onDelete('cascade');

            $table->unsignedBigInteger('dlt_template_group_id')->nullable();
            $table->foreign('dlt_template_group_id')->references('id')->on('dlt_template_groups');

            $table->string('template_name');
            $table->string('dlt_template_id');
            $table->string('entity_id');
            $table->string('sender_id')->comment('sender ID 6 char');
            $table->string('header_id');
            $table->boolean('is_unicode')->default(0);
            $table->text('dlt_message');
            $table->enum('priority',[0,1,2,3])->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');
            $table->boolean('status')->default(0);
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
        Schema::dropIfExists('dlt_templates');
    }
};
