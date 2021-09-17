<?php


namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\ProductExclusiveLocation;
use Carbon\Carbon;
class ProductExclusiveLocationController extends APIController
{
  function __construct(){
    $this->model = new ProductExclusiveLocation();
  }

  public function getByParamsWithLocation($productId, $location){
    $result = ProductExclusiveLocation::where('product_id', '=', $productId)->where('locality', 'like', $location.'%')->get();
    return sizeof($result) > 0 ? $result[0] : null;
  }

  public function getByParams($productId){
    $result = ProductExclusiveLocation::where('product_id', '=', $productId)->get();
    return sizeof($result) > 0 ? $result : null;
  }
}
