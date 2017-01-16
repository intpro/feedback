<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFbmailsTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::create('fbmails', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name')->index();//form_name + '_mail'
            $table->string('form_name')->index();
            $table->string('from');
            $table->string('subject');
            $table->string('to');
            $table->string('username');
            $table->string('email');
            $table->string('body');
            $table->boolean('mailed');
            $table->string('host');
            $table->string('port');
            $table->string('encryption');
            $table->string('report');
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
        Schema::drop('fbmails');
    }
}
