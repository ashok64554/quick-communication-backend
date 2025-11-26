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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('notification_for')->comment('like:forgot-password, report-generate, balance-credited, balance-debited');
            
            $table->string('mail_subject')->nullable();
            $table->text('mail_body')->nullable();

            $table->string('notification_subject')->nullable();
            $table->text('notification_body')->nullable();
            $table->boolean('save_to_database')->default(0);
            
            $table->text('custom_attributes')->nullable();
            $table->string('status_code')->nullable();
            $table->string('route_path')->nullable()->comment('use for redirect');
            $table->boolean('is_deletable')->default(0);
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
        Schema::dropIfExists('notification_templates');
    }
};
