<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Common\Payload\Models\Payload;
use Carbon\Carbon;

class RoomTypeController extends APIController
{
    function __construct(){
      $this->notRequired = array(
        'category', 'details', 'tax', 'person_rate', 'capacity', 'tax', 'code', 'label'
      );
    }

    public function retrieveWithFilter(Request $request){
        $data = $request->all();
        $whereArray = array(
            array('payloads.payload', '=', 'room_type'),
            array('T1.payload', '=', 'room_type')
        );
        $temp = Payload::leftJoin('availabilities as T1', 'T1.payload_value')
            ->leftJoin('room_price_status as T2', 'T2.category', '=', 'payloads.id')
            ->where($whereArray)
            ->limit($data['limit'])
            ->offset($data['offset'])
            ->get();
        if(sizeof($temp) > 0){
            for ($i=0; $i <= sizeof($temp)-1; $i++) { 
                $item = $temp[$i];
            }
        }
    }

    public function createWithImages(Request $request){
        $data = $request->all();
        $exist = Payload::whereRaw("BINARY `payload_value` = ?", [$data['payload_value']])->get();
        if(sizeof($exist) > 0 && $data['status'] === 'create'){
          $this->response['error'] = 'Already Existed';
          $this->response['data'] = null;
          return $this->response();
        }else{
          $payload = array(
            'account_id'    => $data['account_id'],
            'code' => $this->generateCode(),
            'payload' => $data['payload'],
            'category' => $data['category'],
            'payload_value' => $data['payload_value'],
            'details' => isset($data['details']) ? $data['details'] : null,
            'capacity' => $data['capacity'],
            'tax' => $data['tax'] == true ? 1 : 0,
            'person_rate' => $data['person_rate'] == true ? 1 : 0,
            'label' => $data['label']
          );
          if($data['status'] === 'create'){
            $this->model = new Payload();
            $res = $this->insertDB($payload);
          }else if($data['status'] === 'update'){
            $payload['updated_at'] = Carbon::now();
            $res = Payload::where('id', '=', $data['id'])->update($payload);
          }
          if(isset($data['images'])){
            if(sizeof($data['images']) > 0){
              for ($i=0; $i <= sizeof($data['images'])-1 ; $i++) { 
                $item = $data['images'][$i];
                $params = array(
                  'room_id' => $data['status'] === 'create' ? $res['id'] : $data['id'],
                  'url' => $item['url'],
                  'status' => 'room_type'
                );
                app('Increment\Hotel\Room\Http\ProductImageController')->addImage($params);
              }
            }
          }
          $this->response['data'] = $res;
          $this->response['error'] = null;
          return $this->response();
        }
    }

    public function generateCode()
    {
      $code = 'pay_' . substr(str_shuffle($this->codeSource), 0, 60);
      $codeExist = Payload::where('code', '=', $code)->get();
      if (sizeof($codeExist) > 0) {
        $this->generateCode();
      } else {
        return $code;
      }
    }
  
    public function retrieveWithImage(Request $request){
        $data = $request->all();
        $con = $data['condition'];
        $res = Payload::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
          ->where('deleted_at', '=', null)
          ->where('payload', '=', $data['payload'])
          ->offset($data['offset'])->limit($data['limit'])
          ->orderBy(array_keys($data['sort'])[0], array_values($data['sort'])[0])
          ->get();
        
        $size = Payload::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
          ->where('deleted_at', '=', null)
          ->where('payload', '=', $data['payload'])
          ->get();
  
        if(sizeof($res) > 0){
          for ($i=0; $i <= sizeof($res)-1; $i++) {
            $item = $res[$i];
            $res[$i]['image'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImage($item['id']);
          }
        }
        $this->response['data'] = $res;
        $this->response['size'] = sizeOf($size);
        return $this->response();
    }
      
    public function retrieveById(Request $request){
        $data = $request->all();
        $res = Payload::where('id', $data['id'])->first();
        $res['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->getImages($res['id']);
        $res['details'] = json_decode($res['details']);
        $this->response['data'] = $res;
        return $this->response();
    }
  
    public function removeWithImage(Request $request){
        $data = $request->all();
        $isUsed = app('Increment\Hotel\Room\Http\CartController')->getByCategory($data['id']);
        if(sizeof($isUsed) > 0){
            $this->response['data'] = null;
            $this->response['error'] = 'Room Type is Currently in used';
            return $this->response();
        }
        $res = Payload::where('id', '=', $data['id'])->update(array(
            'deleted_at' => Carbon::now()
        ));
        app('Increment\Hotel\Room\Http\ProductImageController')->removeImages($data['id']);
        $this->response['data'] = $res;
        $this->response['error'] = null;
        return $this->response();
    }

    public function retrieveWithAvailability(Request $request){
      $data = $request->all();
      $types = Payload::where('payload', '=', 'room_type')->get(['id', 'payload_value']);
      $addOns  = app('Increment\Hotel\AddOn\Http\AddOnController')->retrieveSelected();
      $availabilty = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveWithCondition($data);
      $this->response['data'] = array(
        'room_types' => $types,
        'add_ons' => $addOns,
        'availability' => $availabilty
      );
      return $this->response();
    }

    public function retrieveRoomTypes(Request $request){
      $data = $request->all();
      $whereArray = array(
        array('payloads.payload', '=', 'room_type'),
        array('T1.limit_per_day', '>', 0),
        array('payloads.capacity', '=', $data['adults']),
        array(function($query)use($data){
          $query->where('T1.start_date', '>=', $data['check_in'])
          ->orWhere('T1.end_date', '<=', $data['check_out']);
        }),
        array('T1.room_price', '>=', $data['min']),
        array('T1.room_price', '<=', $data['max']),
      );
      if($data['priceType'] !== null){
        $whereArray[] = array(function($query)use($data){
          for ($i=0; $i <= sizeof($data['priceType'])-1; $i++) { 
            $item = $data['priceType'][$i];
            $subArray = array();
            if($item['label'] == 'Breakfast only'){
              $subArray[] = array('T1.description', 'like', '%"room_price":"0"%');
            }
            if($item['label'] == 'Room only'){
              $subArray[] = array('T1.description', 'like', '%"break_fast":"0"%');
            }
            if($item['label'] == 'Both'){
              $subArray[] = array(function($query2){
                $query2->where('T1.description', 'not like', '%"room_price":"0"%')
                ->orWhere('T1.description', 'not like', '%"break_fast":"0"%');
              });
            }

            $query->where(function($query3)use($item, $subArray){
              $query3->where($subArray);
            });
          }
        });
      }
      $result = [];
      $temp = Payload::leftJoin('availabilities as T1', 'T1.payload_value', '=', 'payloads.id')->where($whereArray)
        ->orderBy('T1.room_price', 'asc')
        ->groupBy('payloads.id')
        ->get(['T1.id as availabilityId', 'payloads.id as category_id', 'payloads.payload_value as room_type', 'T1.*', 'payloads.capacity',
         'payloads.category as general_description', 'payloads.details as general_features', 'payloads.price_label', 'payloads.code']);
      if(sizeof($temp) > 0){
        for ($i=0; $i <= sizeof($temp)-1 ; $i++) {
          $item = $temp[$i];
          $listPrice = Payload::leftJoin('availabilities as T1', 'T1.payload_value', '=', 'payloads.id')->where($whereArray)->orderBy('T1.room_price', 'asc')->select('T1.*')->first();
          $temp[$i]['availabilityId'] = $listPrice['id'];
          $temp[$i]['description'] = $listPrice['description'];
          $temp[$i]['room_price'] = $listPrice['room_price'];
          $temp[$i]['limit_per_day'] = $listPrice['limit_per_day'];
          $temp[$i]['start_date'] = $listPrice['start_date'];
          $temp[$i]['end_date'] = $listPrice['end_date'];
          $temp[$i]['general_features'] = json_decode($item['general_features']);
          $temp[$i]['description'] = json_decode($item['description']);
          $temp[$i]['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($item['category_id'], 'room_type');
          $isAvailable = app('Increment\Hotel\Room\Http\AvailabilityController')->isAvailable($item['payload_value'], $data['check_in'], $data['check_out']);
          if($isAvailable){
            array_push($result, $item);
          }
        }
      }
      usort($result, function($a, $b) {return (float)$a['room_price'] > (float)$b['room_price'];}); //asc
      $result = array_slice($result, $data['offset'], $data['limit']);
      $this->response['data'] = array(
        'room' => $result,
        'min_max' =>  app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveMaxMin(),
        'pricings' => array(
          array('label' => 'Breakfast only'),
          array('label' => 'Room only'),
          array('label' => 'Both'),
        ),
        'category' => Payload::where('payload', '=', 'room_type')->get(['id', 'payload_value'])
      );
      return $this->response();
    }

    public function retrieveTypesByCode(Request $request){
      $data = $request->all();
      $whereArray = array(
        array('payloads.payload', '=', 'room_type'),
        array('T1.limit_per_day', '>', 0),
        array('payloads.capacity', '=', $data['filter']['adults']),
        array('payloads.id', '=', $data['category_id']),
        array(function($query)use($data){
          $query->where('T1.start_date', '>=', $data['filter']['check_in'])
          ->orWhere('T1.end_date', '<=', $data['filter']['check_out']);
        }),
        array('T1.room_price', '>=', $data['filter']['min']),
        array('T1.room_price', '<=', $data['filter']['max']),
      );

      $temp = Payload::leftJoin('availabilities as T1', 'T1.payload_value', '=', 'payloads.id')->where($whereArray)
        ->groupBy('T1.room_price')
        ->orderBy('T1.room_price', 'asc')
        ->get(['T1.id as availabilityId', 'payloads.id as categoryId', 'payloads.payload_value as room_type', 'T1.*', 'payloads.capacity',
         'payloads.category as general_description', 'payloads.details as general_features', 'payloads.tax', 'payloads.price_label', 'payloads.code']);
      
      $result = [];
      if(sizeof($temp) > 0){
        for ($i=0; $i <= sizeof($temp)-1 ; $i++) { 
          $item = $temp[$i];
          $temp[$i]['general_features'] = json_decode($item['general_features']);
          $temp[$i]['description'] = json_decode($item['description'], true);
          if($temp[$i]['description']['room_price'] == 0 && $temp[$i]['description']['break_fast'] != 0){
            $temp[$i]['room_status'] = array('title' => 'Breakfast Only', 'price' => $temp[$i]['description']['break_fast']);
          }else if($temp[$i]['description']['room_price'] != 0 && $temp[$i]['description']['break_fast'] == 0){
            $temp[$i]['room_status'] = array('title' => 'Room Only', 'price' => $item['room_price']);
          }else if($temp[$i]['description']['room_price'] != 0 && $temp[$i]['description']['break_fast'] != 0){
            $temp[$i]['room_status'] = array('title' => 'Room with Breakfast', 'price' => $item['room_price']);
          }
          array_push($result, $item);
        }
      }
      $this->response['data'] = array(
        'result' => $result,
        'images' => app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($item['categoryId'], 'room_type'),
      );
      return $this->response();
    }

    public function retrieveDetailsByCode(Request $request){
      $data = $request->all();
      $temp = Payload::where('code', '=', $data['code'])->first();
      if($temp !== null){
        $temp['details'] = json_decode($temp['details'], true);
      }
      $this->response['data'] = $temp;
      return $this->response();
    }

    public function getTax($roomType){
      $result = Payload::where('id', '=', $roomType)->first();
      if($result != null){
        if($result['tax'] == 1){
          $tax = Payload::where('payload', '=', 'tax_rate')->first();
          return $tax != null ? $tax['payload'] : 0;
        }else{
          return 0;
        }
      }else{
        return 0;
      }
    }
}
