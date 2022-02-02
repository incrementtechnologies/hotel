<?php

namespace Increment\Hotel\Room\Http;
use Illuminate\Http\Request;
use Increment\Hotel\Reservation\Models\Reservation;
use Increment\Hotel\Room\Models\Cart;
use App\Http\Controllers\APIController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CartController extends APIController
{
    public function __construct(){
        $this->model = new Cart;
    }

    public function create(Request $request){
        $data = $request->all();
        $hasExistingCart = Cart::where('account_id', '=', $data['account_id'])
            ->where(function($query){
                $query->where('status', '=', 'pending')
                ->orWhere('status', '=', 'in_progress');
            })->get();
        if(sizeof($hasExistingCart) > 0){
            $date1 = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', $hasExistingCart[0]['category_id']);
            $date2 = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', $data['category_id']);
            if($date1['start_date'] != $date2['start_date']){
                $this->response['data'] = [];
                $this->response['error'] = 'Cannot Add multiple room with different date';
                return $this->response();
            }
        }else{
            $exist = Cart::where('account_id', '=', $data['account_id'])
                ->where('price_id', '=', $data['price_id'])
                ->where('category_id', '=', $data['category_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })->first();
            if($exist !== null){
                $res = Cart::where('id', '=', $exist['id'])->update(array(
                    'qty' => (int)$exist['qty'] + (int)$data['qty']
                ));
                $this->response['data'] = $res;
                $this->response['error'] = null;
            }else{
                $res = Cart::create($data);
                $this->response['data'] = $res;
                $this->response['error'] = null;
            }
            return $this->response();
        }
    }

    public function countById($priceId, $categoryId){
        // dd($priceId, $categoryId);
        $result = Cart::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where('deleted_at', '=', null)->where(function($query){
            $query->where('status', '=', 'pending')
                ->orWhere('status', '=', 'in_progress')
                ->orWhere('status', '=', 'for_approval')
                ->orWhere('status', '=', 'completed');
        })->sum('qty');
        return $result;
    }

    public function countByCategory($category){
        return Cart::where('category_id', '=', $category)->where('status', '!=', 'pending')->where('deleted_at', '=', null)->count();
    }
    
    public function retrieveByParams(Request $request){
        $data = $request->all();
        if(isset($data['reservation_code'])){
            $reservationId = Reservation::where('reservation_code', '=', $data['reservation_code'])->get(['id']);
            // dd($reservationId[0]['id']);
            $result = Cart::where('carts.account_id', '=', $data['account_id'])
                ->where('reservation_id', '=', $reservationId[0]['id'])
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'price_id', 'reservation_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
            // dd($result);
        }else{
            $result = Cart::where('carts.account_id', '=', $data['account_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'price_id', 'reservation_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $reservation =app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('id', $item['reservation_id'], ['code', 'check_in', 'check_out']);
                if(sizeof($reservation) > 0){
                    $start = Carbon::createFromFormat('Y-m-d H:i:s', $reservation[0]['check_in']);
                    $end = Carbon::createFromFormat('Y-m-d H:i:s', $reservation[0]['check_out']);
                    $nightsDays = $end->diffInDays($start);
                    $result[$i]['date'] = $reservation;
                    $result[$i]['reservation_code'] = sizeOf($reservation) > 0 ? $reservation[0]['code'] : null;
                    $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
                    if($result[$i]['rooms'][0]['label'] === 'MONTH'){
                        $nightsDays = $end->diffInMonths($start);
                    }
                    $result[$i]['price_per_qty'] = ($result[$i]['rooms'][0]['refundable'] !== null ? $result[$i]['rooms'][0]['refundable']  : $result[$i]['rooms'][0]['regular']) * $item['checkoutQty'];
                    $result[$i]['price_with_number_of_days'] = $result[$i]['price_per_qty'] * $nightsDays;
                }
            }
        }
        $this->response['data'] = $result;
        return $this->response();
    }

    public function retrieveCartWithRooms($reservation_id){
        $result = Cart::where('reservation_id', '=', $reservation_id)
            ->groupBy('carts.price_id')
            ->get(['qty', 'reservation_id', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) {
                $temp = [];
                $item = $result[$i];
                $rooms = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
                $result[$i]['rooms'] = $rooms;
                $result[$i]['specificRooms'] = app('Increment\Hotel\Room\Http\RoomController')->retrieveByCategory($item['category_id']);
                if(sizeOf($rooms) > 0){
                    $booking = app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveBookingsByParams('room_id',  $result[$i]['rooms'][0]['id']);
                    if(sizeof($booking) > 0){
                        for ($a=0; $a <= sizeof($booking)-1; $a++) { 
                            $each = $booking[$a];
                            array_push($temp, array(
                                'room_id' => $each['room_id'],
                                'category' => $each['room_type_id']
                            ));
                        }
                    }else{
                        for ($a=0; $a <= $item['qty']-1 ; $a++) {
                            array_push($temp, array(
                                'category' => null
                            ));
                        }
                    }
                }
                $result[$i]['inputs'] = $temp;
            }
        }
        return $result;
    }

    public function retrieveCartWithRoomDetails($reservation_id){
        $result = Cart::where('reservation_id', '=', $reservation_id)
            ->groupBy('carts.price_id')
            ->get(['qty', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) {
                $item = $result[$i];
                $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
            }
        }
        return $result;
    }

    public function retrieveByCondition($condition){
        $result = Cart::where($condition)
            ->groupBy('carts.price_id')
            ->get(['qty', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) {
                $item = $result[$i];
                $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
            }
        }
        return $result;
    }
    
    public function updateByParams($conditions, $updates){
        return Cart::where($conditions)->update($updates);
    }

    public function getByReservationId($reservationId){
        return Cart::where('reservation_id', '=', $reservationId)->where('status', '=', 'for_approval')->groupBy('price_id')->first();
    }

    public function getTotalReservations($priceId, $categoryId){
        return Cart::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where(function($query){
            $query->where('status', '=', 'for_approval')
                ->orwhere('status', '=', 'completed');
        })->sum('qty');
    }

    public function retrieveOwn($params){
        $result = null;
        $account_id = $params['condition'][0]['value']; 
        $whereArray = array(
            array('account_id', '=', $account_id)
        );
        if($params['method'] === 'update'){
            array_push($whereArray, array(
                function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress')
                    ->orWhere('status', '=', 'for_approval')
					->orWhere('status', '=', 'refunded');
                }
            ));
        }else{
            array_push($whereArray, array(
                function($query){
                    $query->where('status', '=', 'pending')
					->orWhere('status', '=', 'cancelled')
                    ->orWhere('status', '=', 'in_progress');
                }
            ));
        }
        if(isset($params['reservation_id'])){
            array_push($whereArray, array(
                'reservation_id', '=', $params['reservation_id']
            ));
        }
        if($params['method'] === 'update'){
            $result = Cart::where($whereArray)
            ->groupBy('carts.price_id')
            ->get(['id', 'qty', 'price_id', 'reservation_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }else{
            $result = Cart::where($whereArray)
            ->groupBy('carts.price_id')
            ->get(['id', 'qty', 'price_id', 'reservation_id', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }
        
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $reservation =app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('id', $item['reservation_id'], ['code']);
                $result[$i]['reservation_code'] = sizeOf($reservation) > 0 ? $reservation[0]['code'] : null;
                $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
            }
        }
        return $result;
    }
}
