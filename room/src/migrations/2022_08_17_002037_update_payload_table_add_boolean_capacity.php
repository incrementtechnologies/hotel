<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePayloadTableAddBooleanCapacity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payloads', function (Blueprint $table) {
            $table->bigInteger('capacity')->after('details')->nullable();
            $table->boolean('person_rate')->after('details')->nullable();
            $table->boolean('tax')->after('details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payloads', function (Blueprint $table) {
            //
        });
    }
}
