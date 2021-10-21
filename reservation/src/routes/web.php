<?php

// Coupons
$route = env('PACKAGE_ROUTE', '').'/reservations/';
$controller = 'Increment\Hotel\Reservation\Http\ReservationController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_web', $controller."retrieveWeb");
Route::post($route.'retrieve_bookings', $controller."retrieveBookings");
Route::post($route.'retrieve_all_details', $controller."retrieveAllDetails");
Route::post($route.'retrieve_details', $controller."retrieveDetails");
Route::post($route.'search', $controller."search");
Route::post($route.'update', $controller."update");
Route::post($route.'update_with_coupon', $controller."updateCoupon");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");