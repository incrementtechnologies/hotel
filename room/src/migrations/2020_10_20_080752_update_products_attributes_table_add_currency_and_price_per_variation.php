<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateProductsAttributesTableAddCurrencyAndPricePerVariation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->double('price',8,2)->after('payload_value')->default(0);
            $table->string('currency')->default('PHP')->after('price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
