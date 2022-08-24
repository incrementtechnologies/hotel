<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Common\Payload\Models\Payload;
use Carbon\Carbon;

class RoomTypeController extends APIController
{
    function __construct(){
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
            'payload' => $data['payload'],
            'category' => $data['category'],
            'payload_value' => $data['payload_value'],
            'details' => isset($data['details']) ? $data['details'] : null,
            'capacity' => $data['capacity'],
            'tax' => $data['tax'],
            'person_rate' => $data['person_rate']
          );
          if($data['status'] === 'create'){
            $this->model = new Payload();
            $res = $this->insertDB($data);
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
      $this->response['data'] = array(
        'room_types' => $types,
        'add_ons' => $addOns
      );
      return $this->response();
    }
}
