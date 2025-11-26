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
        Schema::create('primary_routes', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('created_by')->nullable();

            $table->integer('gateway_type')->default(1)->comment('1:Transactional, 2:Promotional');
            $table->string('route_name');
            $table->bigInteger('smpp_credit')->default(0)->comment('actual SMPP credit');
            $table->integer('coverage')->default(99);
            $table->string('smsc_id');
            $table->string('api_url_for_voice')->nullable();
            $table->string('ip_address');
            $table->integer('port', false);
            $table->integer('receiver_port', false)->nullable()->comment('if transceiver_mode is false, it is TXRX mode');
            $table->string('smsc_username');
            $table->string('smsc_password');
            $table->string('system_type')->default('SMPP');
            $table->integer('throughput')->default(50);
            $table->integer('reconnect_delay')->nullable();
            $table->integer('enquire_link_interval')->nullable();
            $table->integer('max_pending_submits')->nullable();
            $table->boolean('transceiver_mode')->nullable()->default(true);
            $table->integer('source_addr_ton')->default(1);
            $table->integer('source_addr_npi')->default(1);
            $table->integer('dest_addr_ton')->default(1);
            $table->integer('dest_addr_npi')->default(1);
            $table->boolean('log_file')->default(false);
            $table->integer('log_level')->default(1);
            $table->integer('instances')->default(1);
            $table->string('online_from', 50)->nullable();
            $table->boolean('status')->default(1);
            $table->boolean('voice')->default(0)->comment('0:No,1:Yes');

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
        Schema::dropIfExists('primary_routes');
    }
};
