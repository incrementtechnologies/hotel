<?php

// Products
$route = env('PACKAGE_ROUTE', '').'/rooms/';
$controller = 'Increment\Hotel\Room\Http\RoomController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_type_by_code', $controller."retrieveTypeByCode");
Route::post($route.'retrieve_by_type', $controller."retrieveByType");
Route::post($route.'retrieve_by_code', $controller."retrieveByCode");
Route::post($route.'retrieve_unique', $controller."retrieveUnique");
Route::post($route.'retrieve_basic', $controller."retrieveBasic");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'update_with_images', $controller."updateWithImages");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::post($route.'delete_rooms', $controller."deleteRoom");
Route::get($route.'test', $controller."test");

// Pricings
$route = env('PACKAGE_ROUTE', '').'/pricings/';
$controller = 'Increment\\Hotel\Room\Http\PricingController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_pricings', $controller."retrievePricings");
Route::post($route.'retrieve_min_max', $controller."retrieveMaxMin");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


// Product Images
$route = env('PACKAGE_ROUTE', '').'/room_images/';
$controller = 'Increment\Hotel\Room\Http\ProductImageController@';
Route::post($route.'create', $controller."create");
Route::post($route.'create_with_image', $controller."createWithImages");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

//Availability
$route = env('PACKAGE_ROUTE', '').'/availabilities/';
$controller = 'Increment\Hotel\Room\Http\AvailabilityController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_by_code', $controller."retrieveTypeByCode");
Route::post($route.'retrieve_by_id', $controller."retrieveById");
Route::post($route.'get_daily_rate', $controller."dailyRate");
Route::post($route.'retrieve_by_room_type', $controller."retrieveByRoomType");
Route::post($route.'compare_dates', $controller."compareDates");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


//Availability
$route = env('PACKAGE_ROUTE', '').'/carts/';
$controller = 'Increment\Hotel\Room\Http\CartController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'retrieve_carts', $controller."retrieveLocalCart");
Route::post($route.'retrieve_by_date', $controller."getByDate");
Route::post($route.'update', $controller."update");
Route::post($route.'update_qty', $controller."updateQty");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

//RoomTypes
$route = env('PACKAGE_ROUTE', '').'/room_types/';
$controller = 'Increment\Hotel\Room\Http\RoomTypeController@';
Route::post($route.'create', $controller."create");
Route::post($route.'create_with_images', $controller."createWithImages");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_with_images', $controller."retrieveWithImage");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'retrieve_by_date', $controller."getByDate");
Route::post($route.'retrieve_with_availability', $controller."retrieveWithAvailability");
Route::post($route.'retrieve_by_code', $controller."retrieveTypesByCode");
Route::post($route.'retrieve_details_by_code', $controller."retrieveDetailsByCode");
Route::post($route.'retrieve_room_types', $controller."retrieveRoomTypes");
Route::post($route.'update', $controller."update");
Route::post($route.'update_with_images', $controller."createWithImages");
Route::post($route.'delete', $controller."delete");
Route::post($route.'delete_with_images', $controller."removeWithImage");
Route::get($route.'test', $controller."test");

//RoomTypes
$route = env('PACKAGE_ROUTE', '').'/features/';
$controller = 'Increment\Hotel\Room\Http\FeatureController@';
Route::post($route.'create', $controller."create");
Route::post($route.'create_with_images', $controller."createWithImages");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_with_images', $controller."retrieveWithImage");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'retrieve_by_date', $controller."getByDate");
Route::post($route.'retrieve_with_availability', $controller."retrieveWithAvailability");
Route::post($route.'update', $controller."update");
Route::post($route.'update_with_images', $controller."createWithImages");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


