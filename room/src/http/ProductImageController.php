<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\ProductImage;
use Carbon\Carbon;
class ProductImageController extends APIController
{
  function __construct(){
    $this->model = new ProductImage();
  }

  public function createWithImages(Request $request){
    $data = $request->all();
    if(isset($data['url'])){
      if(sizeof($data['url']) > 0){
        for ($i=0; $i <= sizeof($data['url'])-1 ; $i++) { 
          $item = $data['url'][$i];
          $params = array(
            'url' => $item['url'],
            'room_id' => $data['room_id'],
            'status' => 'product'
          );
          $this->addImage($params);
        }
      }
      $this->response['data'] = $data['room_id'];
      return $this->response();
    }else{
      $this->response['error'] = 'Error';
      return $this->response();
    }
  }
  
  public function getProductImage($productId, $status){
  	$result = null;
  	if($status == null){
  		$result = ProductImage::where('product_id', $productId)->orderBy('created_at', 'desc')->get();
  	}else{
  		$result = ProductImage::where('product_id', $productId)->where('status', '=', $status)->orderBy('created_at', 'desc')->get();
  	}
    return (sizeof($result) > 0) ? $result : null;
  }

  public function addImage($data){
    return ProductImage::create($data);
  }

  public function getImage($roomId){
    $image = ProductImage::where('room_id', '=', $roomId)->where('deleted_at', '=', null)->first();
    return $image != null ? $image['url'] : null;
  }

  public function getImages($roomId){
    $image = ProductImage::where('room_id', '=', $roomId)->where('deleted_at', '=', null)->get();
    $res = array();
    if(sizeof($image) > 0){
      for ($i=0; $i <= sizeof($image)-1; $i++) { 
        $item = $image[$i];
        $data = array(
          'url' => $item['url'],
          'id' => $item['id'] 
        );
        array_push($res, $data);
      }
    }
    return $res;
  }

  public function removeImages($roomId){
    return ProductImage::where('room_id', '=', $roomId)->update(array(
      'deleted_at' => Carbon::now()
    ));
  }
}
