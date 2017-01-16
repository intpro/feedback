<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFbformsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fbforms', function(Blueprint $table)
        {
            $table->string('name')->index();
            $table->string('from');
            $table->string('to');
            $table->string('subject');
            $table->string('username');
            $table->string('password');
            $table->string('host');
            $table->string('port');
            $table->string('encryption');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('fbforms');
    }
}
