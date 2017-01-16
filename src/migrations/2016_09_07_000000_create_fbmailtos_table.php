<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFbmailtosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fbmailtos', function(Blueprint $table)
        {
            $table->string('name')->index();//form_name + '_mailto'
            $table->string('form_name')->index();
            $table->increments('id');
            $table->string('to');
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
        Schema::drop('fbmailtos');
    }
}
