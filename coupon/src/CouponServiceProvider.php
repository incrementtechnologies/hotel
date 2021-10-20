<?php

namespace Increment\Hotel\Coupon;

use Illuminate\Support\ServiceProvider;

class CouponServiceProvider extends ServiceProvider{

  public function boot(){
    $this->loadMigrationsFrom(__DIR__.'/migrations');
    $this->loadRoutesFrom(__DIR__.'/routes/web.php');
  }

  public function register(){
  }
}