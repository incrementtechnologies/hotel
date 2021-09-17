<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Room;
use Carbon\Carbon;
class RoomController extends APIController
{
    public $productImageController = 'Increment\Hotel\Room\Http\ProductImageController';
    public $productPricingController = 'Increment\Hotel\Room\Http\PricingController';
    public $inventoryController = 'Increment\Hotel\Room\Http\ProductInventoryController';
    public $productTraceController = 'Increment\Imarket\Trace\Http\ProductTraceController';
    public $merchantController = 'Increment\Account\Merchant\Http\MerchantController';
    function __construct(){
    	$this->model = new Room();
      $this->notRequired = array(
        'tags', 'sku', 'rf', 'category', 'preparation_time', 'inventory_type'
      );
      $this->localization();
    }

    public function create(Request $request){
    	$data = $request->all();
    	$data['code'] = $this->generateCode();
      $data['price_settings'] = 'fixed';
    	$this->model = new Room();
    	$this->insertDB($data);
    	return $this->response();
    }


    public function generateCode(){
      $code = 'PRO-'.substr(str_shuffle($this->codeSource), 0, 60);
      $codeExist = Room::where('code', '=', $code)->get();
      if(sizeof($codeExist) > 0){
        $this->generateCode();
      }else{
        return $code;
      }
    }

    public function retrieve(Request $request){
      $data = $request->all();
      $inventoryType = $data['inventory_type'];
      $accountId = $data['account_id'];
      $this->model = new Room();
      $this->retrieveDB($data);
      $this->response['data'] = $this->manageResult($this->response['data'], null, $inventoryType);
      return $this->response();
    }

    public function retrieveBasic(Request $request){
      $data = $request->all();
      $inventoryType = $data['inventory_type'];
      $accountId = $data['account_id'];
      $this->model = new Room();
      $this->retrieveDB($data);
      $this->response['data'] = $this->manageResultBasic($this->response['data'], null, $inventoryType);
      
      if(sizeof($data['condition']) == 2){
        $condition = $data['condition'];
        $this->response['size'] = Room::where($condition[0]['column'], $condition[0]['clause'], $condition[0]['value'])->where($condition[1]['column'], $condition[1]['clause'], $condition[1]['value'])->count();
      }else if(sizeof($data['condition']) == 1){
        $condition = $data['condition'];
        $this->response['size'] = Room::where($condition[0]['column'], $condition[0]['clause'], $condition[0]['value'])->count();
      }
      
      return $this->response();
    }

    public function retrieveProductById($id, $accountId, $inventoryType = null){
      $inventoryType = $inventoryType == null ? env('INVENTORY_TYPE') : $inventoryType;
      //on wishlist, add parameter inventory type
      //on checkout, add parameter inventory type
      $data = array(
        'condition' => array(array(
          'value'   => $id,
          'column'  => 'id',
          'clause'  => '='
        ))
      );

      $this->model = new Room();
      $this->retrieveDB($data);
      $result = $this->manageResult($this->response['data'], $accountId, $inventoryType);
      return (sizeof($result) > 0) ? $result[0] : null;
    }

    public function getByParams($column, $value){
      $result = Room::where($column, '=', $value)->get();
      return sizeof($result) > 0 ? $result[0] : null;
    }

    public function getByParamsReturnByParam($column, $value, $param){
      $result = Room::where($column, '=', $value)->get();
      return sizeof($result) > 0 ? $result[0][$param] : null;
    }

    public function getProductByParams($column, $value){
      $result = Room::where($column, '=', $value)->get();
      if(sizeof($result) > 0){
        $i= 0;
        foreach ($result as $key) {
          $result[$i]['merchant'] = app($this->merchantController)->getByParams('id', $result[$i]['merchant_id']);
          $result[$i]['featured'] = app($this->productImageController)->getProductImage($result[$i]['id'], 'featured');
          $result[$i]['images'] = app($this->productImageController)->getProductImage($result[$i]['id'], null);
         } 
      }
      return sizeof($result) > 0 ? $result[0] : null;      
    }

    public function getProductByParamsInstallment($column, $value){
      $result = Room::where($column, '=', $value)->get();
      if(sizeof($result) > 0){
        $i= 0;
        foreach ($result as $key) {
          $result[$i]['merchant'] = app($this->merchantController)->getByParams('id', $result[$i]['merchant_id']);
          $result[$i]['featured'] = app($this->productImageController)->getProductImage($result[$i]['id'], 'featured');
          $price = app($this->productPricingController)->getPrice($result[$i]['id']);
          $result[$i]['total'] = $price[0]['price'];
          $result[$i]['currency'] = $price[0]['currency'];
          $result[$i]['price'] = $price;
        }
      }
      return sizeof($result) > 0 ? $result[0] : null;      
    }

    public function manageResultBasic($result, $accountId, $inventoryType){
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $result[$i]['price'] = app($this->productPricingController)->getPrice($result[$i]['id']);
          $result[$i]['featured'] = app($this->productImageController)->getProductImage($result[$i]['id'], 'featured');
          $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i A');
          $result[$i]['inventories'] = null;
          $result[$i]['product_traces'] = null;
          $i++;
        }
      }
      return $result;
    }

    public function manageResult($result, $accountId, $inventoryType){
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $result[$i]['account'] = $this->retrieveAccountDetails($result[$i]['account_id']);
          $result[$i]['price'] = app($this->productPricingController)->getPrice($result[$i]['id']);
          $result[$i]['variation'] = app($this->productAttrController)->getByParams('product_id', $result[$i]['id']);
          $result[$i]['color'] = app($this->productAttrController)->getAttribute($result[$i]['id'], 'Color');
          $result[$i]['size'] = app($this->productAttrController)->getAttribute($result[$i]['id'], 'Size');
          $result[$i]['featured'] = app($this->productImageController)->getProductImage($result[$i]['id'], 'featured');
          $result[$i]['images'] = app($this->productImageController)->getProductImage($result[$i]['id'], null);
          $result[$i]['tag_array'] = $this->manageTags($result[$i]['tags']);
          $result[$i]['created_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['created_at'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i A');
          $result[$i]['inventories'] = null;
          $result[$i]['product_traces'] = null;
          $result[$i]['merchant'] = app($this->merchantController)->getByParams('id', $result[$i]['merchant_id']);
          $i++;
        }
      }
      return $result;
    }

    public function manageTags($tags){
      $result = array();
      if($tags != null){
        if(strpos($tags, ',')){
            $array  = explode(',', $tags);
            if(sizeof($array) > 0){
              for ($i = 0; $i < sizeof($array); $i++) { 
                $resultArray = array(
                  'title' => $array[$i]
                );
                $result[] = $resultArray;
              }
              return $result;
            }else{
              return null;
            }
        }else{
        }
      }else{
        return null;
      }
    }

}
