<?php

// AddOns
$route = env('PACKAGE_ROUTE', '').'/add-on/';
$controller = 'Increment\Hotel\AddOn\Http\AddOnController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_basic', $controller."retrieveBasic");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");