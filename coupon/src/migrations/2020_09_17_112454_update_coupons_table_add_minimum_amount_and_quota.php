<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCouponsTableAddMinimumAmountAndQuota extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('quota')->after('end')->nullable();
            $table->string('minimum_amount')->after('quota')->nullable();
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
