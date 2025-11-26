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
        Schema::create('voice_uploads', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('voiceId')->comment('Internal voice id for send sms through voice API');
            $table->enum('fileStatus',[1,2,3,4])->default(1)->comment('1:Pending, 2:Process, 3:Approved, 4:Rejected, Internal usage');
            $table->string('title');
            $table->string('file_location');
            $table->enum('file_time_duration',[30,60,90,120])->comment('file track length in seconds');
            $table->string('exact_file_duration',5)->comment('exact file track length in seconds');
            $table->string('file_mime_type', 30);
            $table->string('file_extension', 30);
            $table->enum('priority',[0,1,2,3])->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');

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
        Schema::dropIfExists('voice_uploads');
    }
};
