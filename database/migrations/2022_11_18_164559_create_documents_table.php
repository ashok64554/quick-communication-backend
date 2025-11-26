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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code_lang');
            $table->string('title');
            $table->string('slug');
            $table->longText('api_information')->nullable();
            $table->longText('api_code')->nullable();
            $table->longText('response_description')->nullable();
            $table->longText('api_response')->nullable();
            $table->string('video_link')->nullable();
            $table->string('image')->nullable();
            $table->index('code_lang');
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
        Schema::dropIfExists('documents');
    }
};
