<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class RoomController extends APIController
{
  function __construct(){
    $this->model = new Room;
  }

  public function retrieve(Request $request){
    $data = $request->all();
    $con = $data['condition'];
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where($con[0]['column'] === 'created_at' ? 'rooms.'.$con[0]['column'] : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
      ->where('rooms.deleted_at', '=', null)
      ->limit($data['limit'])
      ->offset($data['offset'])
      ->orderBy($con[0]['column'] === 'created_at' ? 'rooms.'.array_keys($data['sort'])[0] : array_keys($data['sort'])[0], array_values($data['sort'])[0])
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label']);
    $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where($con[0]['column'] === 'created_at' ? 'rooms.'.$con[0]['column'] : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
      ->where('rooms.deleted_at', '=', null)
      ->orderBy($con[0]['column'] === 'created_at' ? 'rooms.'.array_keys($data['sort'])[0] : array_keys($data['sort'])[0], array_values($data['sort'])[0])
      ->get();

    for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
      $item = $result[$i];
      $result[$i]['category'] = app('Increment\Common\Payload\Http\PayloadController')->retrieveByParams($item['category']);
      $result[$i]['additional_info'] = json_decode($item['additional_info']);
      $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
    }
    $this->response['data'] = $result;
    $this->response['size'] = sizeof($size);

    return $this->response();
  }

  public function retrieveByType(Request $request){
    $data = $request->all();
    $whereArray = array();
    // if($data['number_of_heads'] > 0){
    //   array_push($whereArray, array(DB::raw('')))
    // }
    if($data['check_in'] !== null && $data['check_out'] !== null){
      array_push($whereArray, array('T3.check_in', '<=', $data['check_in']));
      array_push($whereArray, array('T3.check_out', '>=', $data['check_out']));
    }else{
      if($data['check_in'] !== null){
        array_push($whereArray, array('T3.check_in', '<=', $data['check_in']));
      }
      if($data['check_out'] !== null){
        array_push($whereArray, array('T3.check_out', '>=', $data['check_out']));
      }
    }
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where('T3.payload', '=', 'room')
      ->where('T3.status', '=', 'available')
      ->havingRaw("count(rooms.category) > ?", [$data['number_of_rooms']])
      ->groupBy('rooms.category')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', 'T2.payload_value', 'T2.id as category_id', 'T1.id as price_id']);
    
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countByCategory($item['category']);
        $roomsQty = Room::where('category', $item['category'])->count();
        $result[$i]['fullyBooked'] =  (int)($roomsQty - $addedToCart) > 0 ? false : true;
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
      }
      $this->response['data'] = $result;
      return $this->response();
  }
  
  public function retrieveUnique(Request $request){
    $data = $request->all();
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->where('rooms.category', '=', $data['category_id'])
      ->groupBy('T1.regular')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', DB::raw('COUNT("T1.regular") as room_qty'), 'T1.id as price_id']);
    $images = Room::leftJoin('payloads as T1', 'T1.id', '=', 'rooms.category')
    ->leftJoin('product_images as T2', 'T2.room_id', '=', 'rooms.id')
    ->where('rooms.category', '=', $data['category_id'])
    ->get(['url']);
    
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1; $i++) { 
        $item = $result[$i];
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $roomStatus =  app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveStatus($item['id']);
        $result[$i]['remaining_qty'] = (int)$item['room_qty'] - (int)$addedToCart;
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = $images;
        $result[$i]['isAvailable'] = $roomStatus['status'] === 'available' ? true : false;
      }
    }
    $this->response['data'] = $result;
    return $this->response();
  }
  
  public function retrieveById(Request $request){
    $data = $request->all();
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('rooms.id', '=', $data['room_id'])
      ->where('rooms.deleted_at', '=', null)
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id']);
      
      $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('rooms.id', '=', $data['room_id'])
      ->where('rooms.deleted_at', '=', null)
      ->get();

    for ($i=0; $i <= sizeof($result)-1 ; $i++) {
      $item = $result[$i];
      $result[$i]['category'] = app('Increment\Common\Payload\Http\PayloadController')->retrieveByParams($item['category']);
      $result[$i]['additional_info'] = json_decode($item['additional_info']);
      $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
    }
    $this->response['data'] = $result;
    $this->response['size'] = sizeof($size);

    return $this->response();
  }

  public function updateWithImages(Request $request){
    $data = $request->all();
    $room = array(
      'code' => $data['code'],
      'account_id' => $data['account_id'],
      'title' => $data['title'],
      'category' => $data['category'],
      'description' => $data['description'],
      'additional_info' => $data['additional_info'],
      'status' => $data['status']
    );
    $room['updated_at'] = Carbon::now();
    $res = Room::where('id', '=', $data['id'])->update($room);
    if(isset($data['images'])){
      if(sizeof($data['images']) > 0){
        for ($i=0; $i <= sizeof($data['images'])-1 ; $i++) { 
          $item = $data['images'][$i];
          $params = array(
            'room_id' => $data['id'],
            'url' => $item['url'],
            'status' => 'room_images'
          );
          app('Increment\Hotel\Room\Http\ProductImageController')->addImage($params);
        }
      }
    }
    $this->response['data'] = $res;
    return $this->response();
  }

  public function getWithQty($categoryId, $priceId){
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->where('rooms.category', '=', $categoryId)
      ->groupBy('T1.regular')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', DB::raw('COUNT("T1.regular") as room_qty'), 'T1.id as price_id', 'T2.payload_value']);
    
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
      }
    }
    return $result;
  }
  public function retrieveByParams(Request $request){
    $data = $request->all();
    $result = Room::where('category', '=', $data['category_id'])->where('status', '=', 'publish')->get();
    $this->response['data'] = $result;
    return $this->response();
  }
  public function retrieveByCategory($categoryId){
    return Room::where('category', '=', $categoryId)->get();
  }
}
