<?php

namespace Increment\Hotel\Reservation;

use Illuminate\Support\ServiceProvider;

class ReservationServiceProvider extends ServiceProvider{

  public function boot(){
    $this->loadMigrationsFrom(__DIR__.'/migrations');
    $this->loadRoutesFrom(__DIR__.'/routes/web.php');
  }

  public function register(){
  }
}