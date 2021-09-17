<?php


namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\ProductInventory;
use Carbon\Carbon;
class ProductInventoryController extends APIController
{
    function __construct(){
      $this->model = new ProductInventory();
    }

    public function getInventory($productId){
      $this->localization();
      $result = ProductInventory::where('product_id', '=', $productId)->get();

      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
         $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i');
         $i++; 
        }
      }
      return (sizeof($result) > 0) ? $result : null;
    }

    public function getQty($id){
      return ProductInventory::where('product_id', '=', $id)->sum('qty');
    }
}
