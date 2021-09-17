<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\ProductImage;
class ProductImageController extends APIController
{
  function __construct(){
    $this->model = new ProductImage();
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
}
