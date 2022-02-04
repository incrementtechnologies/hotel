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
    $whereArray = array(
      array('rooms.deleted_at', '=', null),
      array('T3.start_date', '!=', null),
      array('T3.end_date', '!=', null),
      array('rooms.max_capacity', '=', ((int)$data['adults'] + (int)$data['children']))
    );
    if($data['check_in'] !== null && $data['check_out'] !== null){
      array_push($whereArray, array('T3.start_date', '<=', $data['check_in']));
      array_push($whereArray, array('T3.end_date', '>=', $data['check_out']));
    }
    if($data['max'] > 0){
      array_push($whereArray, array('T1.regular', '<=', $data['max']));
      array_push($whereArray, array('T1.regular', '>=', $data['min']));
    }
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->where(function($query)use($data){
        if($data['type'] !== null){
          $query->whereIn('T2.id',  $data['type']);
        }
        if($data['priceType'] !== null){
          $query->whereIn('T1.id',  $data['priceType']);
        }
      })
      ->where('T3.payload', '=', 'room_type')
      ->where('T3.status', '=', 'available')
      ->havingRaw("count(rooms.category) >= ?", [$data['number_of_rooms'] !== null ? $data['number_of_rooms'] : 0])
      ->groupBy('rooms.category')
      ->limit($data['limit'])
      ->offset($data['offset'])
      ->orderBy('T3.start_date', 'desc')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', 'T2.payload_value', 'T2.id as category_id', 'T1.id as price_id', 'T2.category as general_description', 'T2.details as general_features']);
      $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->leftJoin('availabilities as T3', 'T3.payload_value', '=', 'T2.id')
      ->where($whereArray)
      ->where(function($query)use($data){
        if($data['type'] !== null){
          $query->whereIn('T2.id',  $data['type']);
        }
        if($data['priceType'] !== null){
          $query->whereIn('T1.id',  $data['priceType']);
        }
      })
      ->havingRaw("count(rooms.category) > ?", [$data['number_of_rooms'] !== null ? $data['number_of_rooms'] : 0])
      ->groupBy('rooms.category')
      ->get();
    
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countByCategory($item['category']);
        $roomsQty = Room::where('category', $item['category'])->count();
        $result[$i]['fullyBooked'] =  (int)($roomsQty - $addedToCart) > 0 ? false : true;
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
        $result[$i]['general_features'] = json_decode($item['general_features']);
        //get available rooms

        $result[$i]['price'] = null;
        $result[$i]['remaining_qty'] = null;
        $availableRooms = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($item['regular'], $item['category']);
        if($availableRooms !== null && $availableRooms['remaining_qty'] > 0){
          $result[$i]['price'] = $availableRooms['amount'];
          $result[$i]['remaining_qty'] = $availableRooms['remaining_qty'];
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
      $this->response['data']['rooms'] = $result;
      $this->response['size'] = sizeof($size);
      return $this->response();
  }

  public function retrieveUnique(Request $request){
    $data = $request->all();
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->where('rooms.category', '=', $data['category_id'])
      ->where('rooms.max_capacity', '=', $data['heads'])
      ->orderBy('rooms.id', 'desc')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id']);
    $images = Room::leftJoin('payloads as T1', 'T1.id', '=', 'rooms.category')
    ->leftJoin('product_images as T2', 'T2.room_id', '=', 'rooms.id')
    ->where('rooms.category', '=', $data['category_id'])
    ->where('rooms.max_capacity', '=', $data['heads'])
    ->get(['url']);
    $temp = [];
    if(sizeof($result) > 0){
      for ($i=0; $i <= sizeof($result)-1; $i++) {
        $item = $result[$i];
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $roomStatus =  app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveStatus($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $result[$i]['images'] = $images;
        $result[$i]['isAvailable'] = $roomStatus['status'] === 'available' && $item['status'] === 'publish' ? true : false;
        if(sizeof($temp) <= 0){
          array_push($temp, $result[$i]);
        }else{
          for ($a=0; $a <= sizeof($temp)-1; $a++) { 
            $each = $temp[$a];
            $unique = $this->getUnique($temp, $item['regular'], $item['refundable']);
            if($unique === false){
              array_push($temp, $result[$i]);
            }
          }
        }
      }
    }
    if(sizeof($temp) > 0){
      $temp = array_unique((array)$temp);
      $temp = array_values($temp);
      for ($b=0; $b <= sizeof($temp)-1; $b++) { 
        $element = $temp[$b];
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($element['regular'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($element['price_id'], $element['category']);
        $temp[$b]['remaining_qty'] = (int)$rooms['remaining_qty'] - (int)$addedToCart;
      }
    }
    $this->response['data'] = $temp;
    return $this->response();
  }

  public function getUnique($array, $amount1, $amount2){
    $counter = 0;
    for ($i=0; $i <sizeof($array); $i++) { 
      $item = $array[$i];
      if((int)$item['regular'] === (int)$amount1 || (int)$item['refundable'] === (int)$amount2){
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
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', 'T1.id as price_id', 'T1.tax']);
      
      $size = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->where('rooms.code', '=', $data['room_code'])
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
    $result = Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')
      ->leftJoin('payloads as T2', 'T2.id', '=', 'rooms.category')
      ->where('T1.id', '=', $priceId)
      ->where('rooms.category', '=', $categoryId)
      ->groupBy('T1.regular')
      ->get(['rooms.*', 'T1.regular', 'T1.refundable', 'T1.currency', 'T1.label', DB::raw('COUNT("T1.regular") as room_qty'), 'T1.id as price_id', 'T2.payload_value']);
    
    if(sizeof($result) > 0){  
      for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        $item = $result[$i];
        $result[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($item['id']);
        $result[$i]['additional_info'] = json_decode($item['additional_info']);
        $rooms =  app('Increment\Hotel\Room\Http\RoomPriceStatusController')->getTotalByPricesWithDetails($item['regular'], $item['category']);
        $addedToCart  = app('Increment\Hotel\Room\Http\CartController')->countById($item['price_id'], $item['category']);
        $result[$i]['remaining_qty'] = (int)$rooms['remaining_qty'] - (int)$addedToCart;
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

  public function retrieveIDByCode($room_code){
    return Room::where('code', '=', $room_code)->get(['id']);
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

  public function retrieveTotalPriceById($account_id, $column, $value, $returns){
    return Room::leftJoin('pricings as T1', 'T1.room_id', '=', 'rooms.id')->where('T1.'.$column, '=', $value)->where('T1.account_id', '=', $account_id)->get($returns);
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
      $exist = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room', $this->response['data']);
      if($exist === null){
        $params= array(
          'payload' => 'room_id',
          'payload_value' => $this->response['data'],
          'status' => $data['status'] === 'publish' ? 'available' : 'not_available'
        );
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
}
