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
        Schema::create('secondary_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('primary_route_id');
            $table->foreign('primary_route_id')->references('id')->on('primary_routes')->onDelete('cascade');

            $table->unsignedBigInteger('created_by');

            $table->string('sec_route_name');
            $table->boolean('status')->default(1)->comment('0:Inactive, 1:Active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('secondary_routes');
    }
};
