<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Product;
use Carbon\Carbon;
class ProductController extends APIController
{
    public $productImageController = 'Increment\Hotel\Room\Http\ProductImageController';
    public $productAttrController = 'Increment\Hotel\Room\Http\ProductAttributeController';
    public $productPricingController = 'Increment\Hotel\Room\Http\PricingController';
    public $wishlistController = 'Increment\Imarket\Wishlist\Http\WishlistController';
    // public $checkoutController = 'Increment\Imarket\Cart\Http\CheckoutController';
    // public $checkoutItemController = 'Increment\Imarket\Cart\Http\CheckoutItemController';
    public $inventoryController = 'Increment\Hotel\Room\Http\ProductInventoryController';
    public $productTraceController = 'Increment\Imarket\Trace\Http\ProductTraceController';
    public $merchantController = 'Increment\Imarket\Merchant\Http\MerchantController';
    public $bundledProductController = 'Increment\Imarket\Bundled\Http\BundledProductController';
    public $bundledSettingController = 'Increment\Imarket\Bundled\Http\BundledSettingController';
    function __construct(){
    	$this->model = new Product();
      $this->notRequired = array(
        'tags', 'sku', 'rf', 'category', 'preparation_time', 'inventory_type'
      );
      $this->localization();
    }

    public function create(Request $request){
    	$data = $request->all();
    	$data['code'] = $this->generateCode();
      $data['price_settings'] = 'fixed';
    	$this->model = new Product();
    	$this->insertDB($data);
    	return $this->response();
    }


    public function generateCode(){
      $code = 'PRO-'.substr(str_shuffle($this->codeSource), 0, 60);
      $codeExist = Product::where('code', '=', $code)->get();
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
      $this->model = new Product();
      $this->retrieveDB($data);
      $this->response['data'] = $this->manageResult($this->response['data'], null, $inventoryType);
      return $this->response();
    }

    public function retrieveBasic(Request $request){
      $data = $request->all();
      $inventoryType = $data['inventory_type'];
      $accountId = $data['account_id'];
      $this->model = new Product();
      $this->retrieveDB($data);
      $this->response['data'] = $this->manageResultBasic($this->response['data'], null, $inventoryType);
      
      if(sizeof($data['condition']) == 2){
        $condition = $data['condition'];
        $this->response['size'] = Product::where($condition[0]['column'], $condition[0]['clause'], $condition[0]['value'])->where($condition[1]['column'], $condition[1]['clause'], $condition[1]['value'])->count();
      }else if(sizeof($data['condition']) == 1){
        $condition = $data['condition'];
        $this->response['size'] = Product::where($condition[0]['column'], $condition[0]['clause'], $condition[0]['value'])->count();
      }
      
      return $this->response();
    }

    public function getRemainingQty($id){
      // $issued = intval(app($this->checkoutItemController)->getQty('product', $id));
      $total = intval(app($this->inventoryController)->getQty($id));
      // return $total - $issued;
      return $total;
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

      $this->model = new Product();
      $this->retrieveDB($data);
      $result = $this->manageResult($this->response['data'], $accountId, $inventoryType);
      return (sizeof($result) > 0) ? $result[0] : null;
    }

    public function getByParams($column, $value){
      $result = Product::where($column, '=', $value)->get();
      return sizeof($result) > 0 ? $result[0] : null;
    }

    public function getByParamsReturnByParam($column, $value, $param){
      $result = Product::where($column, '=', $value)->get();
      return sizeof($result) > 0 ? $result[0][$param] : null;
    }

    public function getProductByParams($column, $value){
      $result = Product::where($column, '=', $value)->get();
      if(sizeof($result) > 0){
        $i= 0;
        foreach ($result as $key) {
          $result[$i]['merchant'] = app($this->merchantController)->getByParams('id', $result[$i]['merchant_id']);
          $result[$i]['featured'] = app($this->productImageController)->getProductImage($result[$i]['id'], 'featured');
          $result[$i]['images'] = app($this->productImageController)->getProductImage($result[$i]['id'], null);
          $result[$i]['variation'] = app($this->productAttrController)->getByParams('product_id', $result[$i]['id']);
         } 
      }
      return sizeof($result) > 0 ? $result[0] : null;      
    }

    public function getProductByParamsInstallment($column, $value){
      $result = Product::where($column, '=', $value)->get();
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
          $result[$i]['variation'] = app($this->productAttrController)->getByParams('product_id', $result[$i]['id']);
          $result[$i]['color'] = app($this->productAttrController)->getAttribute($result[$i]['id'], 'Color');
          $result[$i]['size'] = app($this->productAttrController)->getAttribute($result[$i]['id'], 'Size');
          if($inventoryType == 'inventory'){
            $result[$i]['qty'] = $this->getRemainingQty($result[$i]['id']);
          }else if($inventoryType == 'product_trace'){
            // $result[$i]['product_traces'] =  app($this->productTraceController)->getByParams('product_id', $result[$i]['id']);
            $qty = app($this->productTraceController)->getBalanceQtyWithInBundled('product_id', $result[$i]['id']);
            $result[$i]['qty'] = $qty['qty'];
            $result[$i]['qty_in_bundled'] = $qty['qty_in_bundled'];
          }
          if($this->installment == true){
            $result[$i]['installment'] = app('Increment\Imarket\Installment\Http\InstallmentController')->getByParams('product_id', $result[$i]['id']); 
          }
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
          $result[$i]['bundled_products'] = app($this->bundledProductController)->getByParams('product_id', $result[$i]['id']);
          $result[$i]['bundled_settings'] = app($this->bundledSettingController)->getByParams('bundled', $result[$i]['id']);
          if($accountId !== null){
            $result[$i]['wishlist_flag'] = app($this->wishlistController)->checkWishlist($result[$i]['id'], $accountId);
            // $result[$i]['checkout_flag'] = app($this->checkoutController)->checkCheckout($result[$i]['id'], $accountId); 
          }
          $result[$i]['inventories'] = null;
          $result[$i]['product_traces'] = null;
          $result[$i]['merchant'] = app($this->merchantController)->getByParams('id', $result[$i]['merchant_id']);
          if($inventoryType == 'inventory'){
            $result[$i]['inventories'] = app($this->inventoryController)->getInventory($result[$i]['id']);
            $result[$i]['qty'] = $this->getRemainingQty($result[$i]['id']);
          }else if($inventoryType == 'product_trace'){
            // $result[$i]['product_traces'] =  app($this->productTraceController)->getByParams('product_id', $result[$i]['id']);
            $qty = app($this->productTraceController)->getBalanceQtyWithInBundled('product_id', $result[$i]['id']);
            $result[$i]['qty'] = $qty['qty'];
            $result[$i]['qty_in_bundled'] = $qty['qty_in_bundled'];
          }
          $result[$i]['installment'] = null;
          if($this->installment == true){
            $result[$i]['installment'] = app('Increment\Imarket\Installment\Http\InstallmentController')->getByParams('product_id', $result[$i]['id']); 
          }
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
