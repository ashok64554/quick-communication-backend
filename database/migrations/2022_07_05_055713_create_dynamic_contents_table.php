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
        Schema::create('dynamic_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dynamic_content_id')->nullable();
            $table->foreign('dynamic_content_id')->references('id')->on('dynamic_contents')->onDelete('cascade');
            
            $table->string('slug');
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->longText('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('order_num')->default('0');
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
        Schema::dropIfExists('dynamic_contents');
    }
};
