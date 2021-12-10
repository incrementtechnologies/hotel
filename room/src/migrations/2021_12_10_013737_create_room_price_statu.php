<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoomPriceStatu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_price_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('price_id');
            $table->bigInteger('category_id');
            $table->double('amount', 8, 2);
            $table->bigInteger('qty');
            $table->string('status');
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
        Schema::dropIfExists('room_price_status');
    }
}
