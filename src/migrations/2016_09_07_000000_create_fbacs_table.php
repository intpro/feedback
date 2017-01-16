<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFbacsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fbacs', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('domain');
            $table->string('host');
            $table->string('port');
            $table->string('encryption');
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
        Schema::drop('fbacs');
    }
}
