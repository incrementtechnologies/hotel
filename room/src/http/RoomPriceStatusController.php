<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use  Increment\Hotel\Room\Models\RoomPriceStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RoomPriceStatusController extends APIController
{
    public function __construct(){
        $this->model = new RoomPriceStatus();
    }

    public function insertPriceStatus($data){
        $exist = RoomPriceStatus::where('amount', '=', $data['amount'])->where('refundable', '=', $data['refundable'])->where('category_id', '=', $data['category_id'])->first();
        if($exist !== null){
           RoomPriceStatus::where('amount', '=', $data['amount'])->where('refundable', '=', $data['refundable'])->where('category_id', '=', $data['category_id'])->update(
               array(
                   'qty' => (int)$exist['qty'] + 1,
               )
           ); 
        }else{
            RoomPriceStatus::create($data);
        }
        return $this->response['data'];
    }

    public function checkIfPriceExist($params){
        return RoomPriceStatus::where($params)->get();
    }

    public function canAdd($priceId, $categoryId, $qty){
        $result = RoomPriceStatus::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where('qty', '>=', $qty)->get();
        return sizeOf($result) > 0;
    }

    public function updateQtyById($id, $value){
        return RoomPriceStatus::where('id', '=', $id)->update(array(
            'qty' => $value,
            'updated_at' => Carbon::now()
        ));
    }
    
    public function updateQtyByPriceId($priceId, $categoryId, $value){
        return RoomPriceStatus::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->update(array(
            'qty' => $value,
            'updated_at' => Carbon::now()
        ));
    }

    public function getTotalByPrice($amount, $categoryId){
        return RoomPriceStatus::where('amount', '=', $amount)->where('category_id', '=', $categoryId)->select(DB::raw('SUM(qty) as qty'), 'price_id', 'category_id', 'amount')->get();
    }
    
    public function getTotalByPricesWithDetails($amount, $refundable, $categoryId){
        $refundable = $refundable > 0 ? $refundable : (double)0;
        $res = null;
        if($amount !== null && $refundable !== null){
            $res = RoomPriceStatus::where('amount', '=', $amount)->where('refundable', '=', $refundable)->where('category_id', '=', $categoryId)->orderBy('amount', 'asc')->first();
        }else{
            $res = RoomPriceStatus::where('category_id', '=', $categoryId)->orderBy('amount', 'asc')->first();
        }
        if($res !== null){  
            $cart = app('Increment\Hotel\Room\Http\CartController')->getTotalReservations($res['price_id'], $res['category_id']);
            $res['remaining_qty'] = (int)$res['qty'] - $cart;
        }
        return $res;
    }
    
    public function updateByParams($condition, $update){
        return RoomPriceStatus::where($condition)->update($update);
    }

    public function getTotalRoomsAddedPerCategory($category){
        return RoomPriceStatus::where('category_id', '=', $category)->sum('qty');
    }

    public function getTotalAdded($condition){
        return RoomPriceStatus::where($condition)->sum('qty');
    }

    public function getByAmount($priceId, $category, $amount){
        return RoomPriceStatus::where('price_id', '=', $priceId)->where('category_id', '=', $category)->where('amount', '=', $amount)->first();
    }
}
