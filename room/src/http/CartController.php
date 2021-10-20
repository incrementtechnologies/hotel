<?php

namespace Increment\Hotel\Room\Http;
use Illuminate\Http\Request;
use  Increment\Hotel\Room\Models\Cart;
use App\Http\Controllers\APIController;
use Illuminate\Support\Facades\DB;

class CartController extends APIController
{
    public function __construct(){
        $this->model = new Cart;
    }

    public function create(Request $request){
        $data = $request->all();
        $res = Cart::create($data);
        $this->response['data'] = $res;
        return $this->response();
    }

    public function countById($priceId, $categoryId){
        return Cart::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where('deleted_at', '=', null)->count();
    }
    
    public function retrieveByParams(Request $request){
        $data = $request->all();
        $result = Cart::where('carts.account_id', '=', $data['account_id'])->where('carts.status', '!=', $data['status'])
            ->groupBy('carts.price_id')
            ->get(['qty', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
            }
        }
        $this->response['data'] = $result;
        return $this->response();
    }

    public function updateByParams($conditions, $updates){
        return Cart::where($conditions)->update($updates);
    }
}
