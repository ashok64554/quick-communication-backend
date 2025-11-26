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
        Schema::create('whats_app_templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('whats_app_configuration_id')->nullable();
            $table->foreign('whats_app_configuration_id')->references('id')->on('whats_app_configurations')->onDelete('cascade');

            $table->string('wa_template_id')->nullable();
            $table->string('parameter_format')->nullable()->comment('POSITIONAL, NAMED');
            $table->enum('category',['Marketing', 'Utility','Authentication']);
            $table->string('sub_category')->nullable();
            $table->string('marketing_type')->nullable();
            $table->string('template_language');
            $table->string('template_name');
            $table->enum('template_type',['none','TEXT', 'MEDIA','CATALOG'])->nullable();
            $table->string('header_text')->nullable();
            $table->text('header_variable')->nullable();
            $table->enum('media_type',['DOCUMENT', 'IMAGE','VIDEO','LOCATION','CATALOG'])->nullable()->comment('when template_type is MEDIA');
            $table->longText('header_handle')->nullable();
            $table->longText('message')->nullable();
            $table->text('message_variable')->nullable();
            $table->text('footer_text')->nullable();
            $table->boolean('button_action')->nullable()->comment('0:Call to action,1:Quick Reply');
            $table->enum('status', ['0','1','2','3'])->default('0')->comment('0:Pending,1:Submitted,2:Approved,3:Reject');
            $table->string('wa_status')->nullable();
            $table->tinyInteger('priority')->default(0)->comment('range 0-3 is allowed, Defaults to 0, which is the lowest priority');
            $table->text('tags')->nullable()->comment('for better search and understanding');
            $table->longText('json_response')->nullable()->comment('for any missing parameter & better understanding');
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
        Schema::dropIfExists('whats_app_templates');
    }
};
