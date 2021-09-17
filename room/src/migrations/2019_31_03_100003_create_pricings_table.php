<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePricingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pricings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id');
            $table->bigInteger('product_id');
            $table->string('type');
            $table->integer('minimum')->nullable();
            $table->integer('maximum')->nullable();
            $table->double('price');
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
        Schema::dropIfExists('pricings');
    }
}
