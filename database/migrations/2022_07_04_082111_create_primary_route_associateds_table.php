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
        Schema::create('primary_route_associateds', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_route_id');
            $table->foreign('primary_route_id')->references('id')->on('primary_routes')->onDelete('cascade');

            $table->unsignedBigInteger('associted_primary_route');
            $table->foreign('associted_primary_route')->references('id')->on('primary_routes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('primary_route_associateds');
    }
};
