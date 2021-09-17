<?php

// Products
$route = env('PACKAGE_ROUTE', '').'/rooms/';
$controller = 'Increment\Imarket\Product\Http\ProductController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_basic', $controller."retrieveBasic");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

// Pricings
$route = env('PACKAGE_ROUTE', '').'/pricings/';
$controller = 'Increment\Imarket\Product\Http\PricingController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

// Product Attributes
$route = env('PACKAGE_ROUTE', '').'/product_attributes/';
$controller = 'Increment\Imarket\Product\Http\ProductAttributeController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


// Product Images
$route = env('PACKAGE_ROUTE', '').'/product_images/';
$controller = 'Increment\Imarket\Product\Http\ProductImageController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

// Product Inventories
$route = env('PACKAGE_ROUTE', '').'/product_inventories/';
$controller = 'Increment\Imarket\Product\Http\ProductInventoryController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


// Product Exclusive Locations
$route = env('PACKAGE_ROUTE', '').'/product_exclusive_locations/';
$controller = 'Increment\Imarket\Product\Http\ProductExclusiveLocationController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

