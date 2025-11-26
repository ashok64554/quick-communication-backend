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
        Schema::create('voice_upload_sent_gateways', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('voice_upload_id')->nullable();
            $table->foreign('voice_upload_id')->references('id')->on('voice_uploads')->onDelete('cascade');

            $table->string('primary_route_id')->nullable();
            $table->string('file_send_to_smsc_id')->nullable()->comment('which vendor will this file go to for approval');
            $table->string('voice_id')->nullable()->comment('comes form gateway provider');
            $table->enum('file_status',[1,2,3,4])->default(1)->comment('1:Pending, 2:Process, 3:Approved, 4:Rejected');

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
        Schema::dropIfExists('voice_upload_sent_gateways');
    }
};
