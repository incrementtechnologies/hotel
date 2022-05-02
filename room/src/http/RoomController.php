<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Room;
use Increment\Hotel\Room\Models\Availability;
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
      ->get(['rooms.*', 'T1.regular', 'T1.tax_price', 'T1.tax', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id']);
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
      $result[$i]['isUsed'] = false;
      $inCart = app('Increment\Hotel\Room\Http\CartController')->retrieveByPriceId($item['price_id']);
      if(sizeof($inCart) > 0){
        $isAssigned = app('Increment\Hotel\Reservation\Http\ReservationController')->getAssignedQtyByParams('reservation_id', $inCart[0]['reservation_id']);
        if($isAssigned > 0){
          $result[$i]['isUsed'] = true;
        }
      }

    }
    $this->response['data'] = $result;
    $this->response['size'] = sizeof($size);

    return $this->response();
  }

  public function retrieveByType(Request $request){
    $data = $request->all();
    $whereArray = array(
      array('rooms.deleted_at', '=', null),
      array('rooms.max_capacity', '>=', ((int)$data['adults'] + (int)$data['children'])),
      array('rooms.max_capacity', '>', 0),
      array('T3.payload', '=', 'room_type'),
      array('T3.status', '=', 'available'),
      array('rooms.status', '=', 'publish')
    );
    if($data['check_in'] !== null && $data['check_out'] !== null){
      array_push($whereArray, array('T3.start_date', '<=', $data['check_in']));
      array_push($whereArray, array('T3.end_date', '>=', $data['check_out']));
    }
    if($data['max'] > 0){
      array_push($whereArray, array(function($query)use($data){
        $query->where('T1.tax_price', '<=', $data['max'])
        ->orWhere('T1.tax_price', 'like', '%'.$data['max'].'%');
      }));
      array_push($whereArray, array(function($query)use($data){
        $query->where('T1.tax_price', '>=', $data['min'])
        ->orWhere('T1.tax_price', 'like', '%'.$data['min'].'%');
      }));
    }
    
    if($data['priceType'] !== null){
      $whereArray[] = array(function($query)use($data){
        for ($i=0; $i <= sizeof($data['priceType'])-1; $i++) { 
          $item = $data['priceType'][$i];
          $subArray = array(
            array('T1.label', '=', strpos($item['label'], 'night') ? 'per night' : 'per month')
          );
          if(strpos($item['label'], 'tax')){
            $subArray[] = array('T1.tax', '=', 1);
          }else{
            $subArray[] = array('T1.tax', '=', 0);
          }
          $query->where(function($query2)use($item, $subArray){
              $query2->where($subArray);
          })->orWhere(function($query3)use($item, $subArray){
            $query3->where($subArray);
          });
        }
      });
    }
    if($data['type'] !== null){
      $whereArray[] = array(function($query)use($data){
        for ($i=0; $i <= sizeof($data['type'])-1 ; $i++) { 
          $item = $data['type'][$i];
          $query->orWhere('rooms.category', '=', $item);
        }
      });
    }
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->havingRaw("count(rooms.category) >= ?", [$data['number_of_rooms'] !== null ? $data['number_of_rooms'] : 0])
      ->groupBy('rooms.category')
      ->limit($data['limit'])
      ->offset($data['offset'])
      ->orderBy('T3.start_date', 'desc')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.tax_price', 'T1.tax', 'T1.currency', 'T1.label', 'T2.payload_value', 'T2.id as category_id', 'T1.id as price_id', 'T2.category as general_description', 'T2.details as general_features']);
    $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->havingRaw("count(rooms.category) >= ?", [$data['number_of_rooms'] !== null ? $data['number_of_rooms'] : 0])
      ->groupBy('rooms.category')
      ->orderBy('T3.start_date', 'desc')
      ->get();
    
    $finalResult = [];
    for ($i=0; $i <= sizeof($result)-1 ; $i++) {
      $item = $result[$i];
      $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countByCategory($item['category']);
      $roomsQty = Room::where('category', $item['category'])->count();
      $result[$i]['fullyBooked'] =  (int)($roomsQty - $addedToCart) > 0 ? false : true;
      $result[$i]['additional_info'] = json_decode($item['additional_info']);
      $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($item['category_id'], 'room_type');
      $result[$i]['general_features'] = json_decode($item['general_features']);
      $result[$i]['tax_price'] = number_format($item['tax_price'], 2, '.', '');
      //get available rooms

      $result[$i]['price'] = null;
      $result[$i]['remaining_qty'] = null;
      $availableRooms = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails(null, null, $item['category']);
      if($availableRooms !== null && $availableRooms['remaining_qty'] > 0){
        $result[$i]['price'] = number_format($availableRooms['amount'], 2, '.', '');
        $result[$i]['remaining_qty'] = $availableRooms['remaining_qty'];
      }
      
      $categoryAvailable = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', $item['category']);
      $hasRoom = Room::where('category', '=', $item['category_id'])->get();
      $data['category_id'] = $item['category'];
      $hasRooms = $this->hasRoom($data);
      if($categoryAvailable !== null && sizeof($hasRoom) > 0 && sizeof($hasRooms) > 0){
        if($addedToCart < (int)$categoryAvailable['limit']){
          array_push($finalResult, $result[$i]);
        }
      }
    }
    if($data['flag'] === 'false'){
      $pricings = app('Increment\Hotel\Room\Http\PricingController')->retrieveLabel();
      $minMax = app('Increment\Hotel\Room\Http\PricingController')->retrieveMaxMin();
      $category = app('Increment\Common\Payload\Http\PayloadController')->retrieveAllData();
      $this->response['data']['pricings'] = $pricings;
      $this->response['data']['min_max'] = $minMax;
      $this->response['data']['category'] = $category;
    }
    $this->response['data']['rooms'] = $finalResult;
    $this->response['size'] = sizeof($size);
    return $this->response();
  }

  public function retrieveUnique(Request $request){
    $data = $request->all();
    $whereArray = array(
      array('rooms.category', '=', $data['category_id']),
      array('rooms.deleted_at', '=', null),
      array('rooms.max_capacity', '>=', ((int)$data['filter']['adults'] + (int)$data['filter']['children'])),
      array('rooms.max_capacity', '>', 0),
    );
    if($data['filter']['check_in'] !== null && $data['filter']['check_out'] !== null){
      array_push($whereArray, array('T3.start_date', '<=', $data['filter']['check_in']));
      array_push($whereArray, array('T3.end_date', '>=', $data['filter']['check_out']));
    }
    if($data['filter']['max'] > 0){
      array_push($whereArray, array('T1.tax_price', '<=', $data['filter']['max']));
      array_push($whereArray, array('T1.tax_price', '>=', $data['filter']['min']));
    }
    if($data['filter']['priceType'] !== null){
      $whereArray[] = array(function($query)use($data){
        for ($i=0; $i <= sizeof($data['filter']['priceType'])-1; $i++) { 
          $item = $data['filter']['priceType'][$i];
          $subArray = array(
            array('T1.label', '=', strpos($item['label'], 'night') ? 'per night' : 'per month')
          );
          if(strpos($item['label'], 'tax')){
            $subArray[] = array('T1.tax', '=', 1);
          }else{
            $subArray[] = array('T1.tax', '=', 0);
          }
          $query->where(function($query2)use($item, $subArray){
              $query2->where($subArray);
          })->orWhere(function($query3)use($item, $subArray){
            $query3->where($subArray);
          });
        }
      });
    }
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->where('rooms.status', '=', 'publish')
      ->orderBy('T1.tax_price', 'desc')
      ->get(['rooms.*', 'T1.regular', 'T1.tax_price', 'T1.tax', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id']);
    $images = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($data['category_id'], 'room_type');
    $temp = [];
    $finalResult = [];
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1; $i++) {
        $item = $result[$i];
        // $assignedRooms = app('Increment\Hotel\Reservation\Http\ReservationController')->getAssignedRoomsQty();
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $roomStatus =  app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveStatus($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = $images;
        $result[$i]['isAvailable'] = $roomStatus !== null && $roomStatus['status'] === 'available' && $item['status'] === 'publish' ? true : false;
        // if(sizeof($temp) <= 0){
        if(sizeof($temp) <= 0){
          if($result[$i]['isAvailable'] === true){
            array_push($temp, $result[$i]);
          }
        }else{
          $unique = array_filter($temp, function($el)use($item){
            return (int)$item['tax_price'] === (int)$el['tax_price'] && (int)$item['refundable'] === (int)$el['refundable'];
          });
          if(sizeof($unique) <= 0){
            // array_push($temp, $result[$i]);
            if($result[$i]['isAvailable'] === true){
              array_push($temp, $result[$i]);
            }
          }
        }
        // }else{
        //   for ($a=0; $a <= sizeof($temp)-1; $a++) { 
        //     $each = $temp[$a];
        //     $unique = $this->getUnique($temp, $item['tax_price'], $item['refundable']);
        //     if($unique === false){
        //       array_push($temp, $result[$i]);
        //     }
        //   }
        // }
      }
    }
    if(sizeof($temp) > 0){
      $temp = array_unique((array)$temp);
      $temp = array_values($temp);
      for ($b=0; $b <= sizeof($temp)-1; $b++) { 
        $element = $temp[$b];
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($element['tax_price'], $element['refundable'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($element['price_id'], $element['category']);
        $temp[$b]['tax_price'] = number_format($element['tax_price'], 2, '.', '');
        $temp[$b]['remaining_qty'] = $rooms!== null ? (int)$rooms['remaining_qty'] : 0;
        if((int)$temp[$b]['remaining_qty'] > 0){
          array_push($finalResult, $temp[$b]);
        }
      }
    }
    $this->response['data'] = $finalResult;
    return $this->response();
  }

  public function hasRoom($data){
    $whereArray = array(
      array('rooms.category', '=', $data['category_id']),
      array('rooms.deleted_at', '=', null),
      array('rooms.max_capacity', '>=', ((int)$data['adults'] + (int)$data['children'])),
      array('rooms.max_capacity', '>', 0),
    );
    if($data['check_in'] !== null && $data['check_out'] !== null){
      array_push($whereArray, array('T3.start_date', '<=', $data['check_in']));
      array_push($whereArray, array('T3.end_date', '>=', $data['check_out']));
    }
    if($data['max'] > 0){
      array_push($whereArray, array('T1.tax_price', '<=', $data['max']));
      array_push($whereArray, array('T1.tax_price', '>=', $data['min']));
    }
    if($data['priceType'] !== null){
      $whereArray[] = array(function($query)use($data){
        for ($i=0; $i <= sizeof($data['priceType'])-1; $i++) { 
          $item = $data['priceType'][$i];
          $subArray = array(
            array('T1.label', '=', strpos($item['label'], 'night') ? 'per night' : 'per month')
          );
          if(strpos($item['label'], 'tax')){
            $subArray[] = array('T1.tax', '=', 1);
          }else{
            $subArray[] = array('T1.tax', '=', 0);
          }
          $query->where(function($query2)use($item, $subArray){
              $query2->where($subArray);
          })->orWhere(function($query3)use($item, $subArray){
            $query3->where($subArray);
          });
        }
      });
    }
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->where('rooms.status', '=', 'publish')
      ->orderBy('T1.tax_price', 'desc')
      ->get(['rooms.*', 'T1.regular', 'T1.tax_price', 'T1.tax', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id']);
    $images = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($data['category_id'], 'room_type');
    $temp = [];
    $finalResult = [];
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1; $i++) {
        $item = $result[$i];
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $roomStatus =  app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveStatus($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = $images;
        $result[$i]['isAvailable'] = $roomStatus !== null && $roomStatus['status'] === 'available' && $item['status'] === 'publish' ? true : false;
        if($result[$i]['isAvailable'] === true){
          array_push($temp, $result[$i]);
        }
      }
    }
    if(sizeof($temp) > 0){
      $temp = array_unique((array)$temp);
      $temp = array_values($temp);
      for ($b=0; $b <= sizeof($temp)-1; $b++) { 
        $element = $temp[$b];
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($element['tax_price'], $element['refundable'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($element['price_id'], $element['category']);
        $temp[$b]['remaining_qty'] = $rooms!== null ? (int)$rooms['remaining_qty'] : 0;
        if((int)$temp[$b]['remaining_qty'] > 0){
          array_push($finalResult, $temp[$b]);
        }
      }
    }
    return $finalResult;
  }

  public function getUnique($array, $amount1, $amount2){
    $counter = 0;
    for ($i=0; $i <sizeof($array); $i++) { 
      $item = $array[$i];
      if((int)$item['regular'] === (int)$amount1 && (int)$item['refundable'] === (int)$amount2){
        $counter += 1;
      }
    }
    if($counter > 0){
      return true;
    }else{
      return false;
    }
  }
  
  public function retrieveByCode(Request $request){
    $data = $request->all();
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('rooms.code', '=', $data['room_code'])
      ->where('rooms.deleted_at', '=', null)
      ->get(['rooms.*', 'T1.regular', 'T1.tax_price', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id', 'T1.tax']);
      
    $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('rooms.code', '=', $data['room_code'])
      ->where('rooms.deleted_at', '=', null)
      ->get();
    for ($i=0; $i <= sizeof($result)-1 ; $i++) {
      $item = $result[$i];
      $totalAddOns = 0;
      $result[$i]['category'] = app('Increment\Common\Payload\Http\PayloadController')->retrieveByParams($item['category']);
      $result[$i]['additional_info'] = json_decode($item['additional_info']);
      if(sizeOf($result[$i]['additional_info']->add_ons) > 0){
        for ($a=0; $a <= sizeOf($result[$i]['additional_info']->add_ons)-1 ; $a++) { 
          $el = $result[$i]['additional_info']->add_ons[$a];
          $totalAddOns = (float)$totalAddOns + $el->price;
        }
      }
      $result[$i]['regular'] = (float)$result[$i]['regular'] - $totalAddOns;
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
    $id = Room::where('code', '=', $data['id'])->get(['id']);
    $res = Room::where('id', '=', $id[0]['id'])->update($room);
    $availID = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_id', $id[0]['id']);
    $avail = array(
      'payload' => 'room_id',
      'payload_value' => $id[0]['id'],
      'status' => $data['status'] === 'publish' ? 'available' : 'not_available'
    );
    $avail['updated_at'] = Carbon::now();
    $con = array(
      'id' => $availID['id']
    );
    $availIDs = app('Increment\Hotel\Room\Http\AvailabilityController')->updateByParams($con, $avail);
    if(isset($data['images'])){
      if(sizeof($data['images']) > 0){
        for ($i=0; $i <= sizeof($data['images'])-1 ; $i++) {
          $item = $data['images'][$i];
          $params = array(
            'room_id' => $id[0]['id'],
            'url' => $item['url'],
            'status' => 'room_images'
          );
          app('Increment\Hotel\Room\Http\ProductImageController')->addImage($params);
        }
      }
    }
    $this->response['data'] = $res === 1 ? $id[0]['id'] : $res;
    return $this->response();
  }

  public function getWithQty($categoryId, $priceId){
    $result = DB::table('rooms')->leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->where('T1.id', '=', $priceId)
      ->where('rooms.category', '=', $categoryId)
      ->groupBy('T1.tax_price')
      ->get(['rooms.*', 'T1.regular', 'T1.tax', 'T1.tax_price', 'T1.refundable', 'T1.currency', 'T1.label', DB::raw('COUNT("T1.tax_price") as room_qty'), 'T1.id as price_id', 'T2.payload_value']);
    $result = json_decode(json_encode($result), true);
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $result[$i]['tax_price'] = number_format($item['tax_price'], 2, '.', '');
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($item['category'], 'product');
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($item['tax_price'], $item['refundable'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $result[$i]['remaining_qty'] = $rooms !== null ? (int)$rooms['remaining_qty'] : 0;
        $result[$i]['total_room_qty'] = $rooms !== null ? (int)$rooms['qty'] : 0;
        $result[$i]['added_to_cart'] = (int)$addedToCart;
      }
    }
    return $result;
  }

  public function getRoomDetails($categoryId, $priceId, $limit){
    $pricings = DB::table('pricings')->where('pricings.id', '=', $priceId)->first();
    $result = [];
    if($pricings){
      $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
        ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
        ->where('rooms.category', '=', $categoryId)
        ->where('T1.tax_price', '=', $pricings->tax_price)
        ->where('T1.label', '=', $pricings->label)
        ->limit($limit)
        ->get(['rooms.*', 'T1.regular', 'T1.tax', 'T1.tax_price', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id', 'T2.payload_value']);
    }
    
    if(sizeof($result) > 0){  
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $result[$i]['tax_price'] = number_format($item['tax_price'], 2, '.', '');
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($item['regular'], $item['refundable'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $result[$i]['remaining_qty'] = $rooms !== null ? (int)$rooms['remaining_qty'] - (int)$addedToCart : 0;
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
    return Room::where('category', '=', $categoryId)->where('status', '=', 'publish')->get();
  }

  public function retrieveIDByCode($room_code){
    return Room::where('code', '=', $room_code)->get(['id']);
  }

  public function availableRoomByCapacity($category, $capacity){
    return Room::where('category', '=', $category)->where('max_capacity', '>=', $capacity)->where('status', '=', 'publish')->get(['id']);
  }
  public function retrieveTypeByCode(Request $request){
    $data = $request->all();
    $result = Room::where('code', '=', $data['code'])->get(['category']);
    $this->response['data'] = $result;
    return $this->response();
  }


  public function retrieveByIDParams($id){
    return Room::where('id', '=', $id)->get();
  }

  public function retrieveByFilter($priceId, $categoryId){
    $pricings = app('Increment\Hotel\Room\Http\PricingController')->retrieveByColumn('id', $priceId);
    if($pricings !== null){
      $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
        ->where('T1.tax_price', '=', $pricings['tax_price'])
        ->where('T1.tax', '=', $pricings['tax'])
        ->where('T1.refundable', '=', $pricings['refundable'])
        ->where('T1.label', '=', $pricings['label'])->get();
      
      return $result;
    }else{
      return null;
    }
  }

  public function retrieveTotalPriceById($account_id, $column, $value, $returns){
    return Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('T1.'.$column, '=', $value)
      ->where('T1.account_id', '=', $account_id)
      ->where('rooms.status', '=', 'publish')
      ->get($returns);
  }

  public function create(Request $request){
    $data = $request->all();
    $exist = Room::where('title', '=', $data['title'])->get();
    if(sizeof($exist) > 0){
      $this->response['data'] = null;
      $this->response['error'] = 'Room title already exist';
    }else{
      $this->model = new Room();
      $data['code'] = $this->generateCode();
      $this->insertDB($data);
      $exist = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_id', $this->response['data']);
      if($exist === null){
        $roomType = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', $data['category']);
        $params= array(
          'payload' => 'room_id',
          'payload_value' => $this->response['data'],
          'status' => $data['status'] === 'publish' ? 'available' : 'not_available'
        );
        if($roomType !== null){
          $params['start_date'] = $roomType['start_date'];
          $params['end_date'] = $roomType['end_date'];
        }
        $res = app('Increment\Hotel\Room\Http\AvailabilityController')->createByParams($params);
      }
    }
    return $this->response();
  }

  public function generateCode()
	{
		$code = 'room_' . substr(str_shuffle($this->codeSource), 0, 60);
		$codeExist = Room::where('code', '=', $code)->get();
		if (sizeof($codeExist) > 0) {
			$this->generateCode();
		} else {
			return $code;
		}
	}


  public function updateByParams($condition, $params){
    return Room::where($condition)->update($params);
  }
  
  public function deleteRoom(Request $request){
    $data = $request->all();
    $con = $data['condition'];
    $room = Room::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])->first();
    if($room !== null){
      $roomdeleted = Room::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])->update(array(
        'deleted_at' => Carbon::now()
      ));

      $pricing = app('Increment\Hotel\Room\Http\PricingController')->retrieveByColumn('room_id', $room['id']);
      if($pricing !== null){
        $priceStatus = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->checkIfPriceExist(array(
          array('amount', '=', $pricing['tax_price']),
          array('refundable', '=', $pricing['refundable'] !== null ? $pricing['refundable'] : (double)0),
          array('category_id', '=', $room['category']),
          array('deleted_at', '=', null)
        ));
      }
      $condition = array(
        array('amount', '=', $pricing['tax_price']),
        array('refundable', '=', $pricing['refundable'] !== null ? $pricing['refundable'] : (double)0),
        array('category_id', '=', $room['category']),
      );
      $update = null;
      if(sizeOf($priceStatus) > 0){
        if($priceStatus[0]['qty'] === 1){
          $update = array(
            'deleted_at' => Carbon::now()
          );
        }else{
          $update = array(
            'qty' => (int)$priceStatus[0]['qty'] - 1
          );
        }
      }
      app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateByParams($condition, $update);
      app('Increment\Hotel\Room\Http\PricingController')->deleteByColumn('room_id', $room['id']);
      app('Increment\Hotel\Room\Http\AvailabilityController')->updateByParams(
        array(
          array('payload_value', '=', $room['id']),
          array('payload', '=', 'room_id')
        ),
        array(
          'deleted_at' => Carbon::now()
        )
      );
      $this->response['data'] = $roomdeleted;
      return $this->response();
    }
  }

  public function checkIfExist($column, $clause, $value){
    return Room::where($column, $clause, $value)->first();
  }
}
