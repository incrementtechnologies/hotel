<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Room;
use Carbon\Carbon;
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
}
