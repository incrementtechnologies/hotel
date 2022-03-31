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
        $emptyCart = Cart::where('account_id', '=', $data['account_id'])->where('deleted_at', '=', null)->get();
        $existingCart = Cart::where('account_id', '=', $data['account_id'])
            ->where('check_in', 'not like', '%'.$data['check_in'].'%')
            ->where('check_out', 'not like', '%'.$data['check_out'].'%')
            ->where(function($query){
                $query->where('status', '=', 'pending')
                ->orWhere('status', '=', 'in_progress');
            })->where('deleted_at', '=', null)
            ->first();
        if($existingCart !== null &&  sizeof($emptyCart) > 0){
            $this->response['data'] = [];
            $this->response['error'] = 'Cannot Add multiple room with different date';
            return $this->response();
        }else{
            $exist = Cart::where('account_id', '=', $data['account_id'])
                ->where('price_id', '=', $data['price_id'])
                ->where('category_id', '=', $data['category_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress')
                    ->orWhere('status', '=', 'for_approval');
                })->first();
            if($exist !== null){
                $hasReservations = Cart::where('id', '=', $exist['id'])->first();
                $addedQty =  (int)$exist['qty'] + (int)$data['qty'];
                $res = null;
                if($hasReservations !== null){
                    $canAdd = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->canAdd($data['price_id'], $data['category_id'], $addedQty);
                    if(!$canAdd){
                        $this->response['data'] = null;
                        $this->response['error'] = 'Qty in carts exceeds the available qty';
                        return $this->response();
                    }else{
                        $res = Cart::where('id', '=', $exist['id'])->update(array(
                            'qty' => $addedQty
                        ));
                    }
                }else{
                    $canAdd = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->canAdd($data['price_id'], $data['category_id'], (int)$data['qty']);
                    if(!$canAdd){
                        $this->response['data'] = null;
                        $this->response['error'] = 'Qty in carts exceeds the available qty';
                        return $this->response();
                    }else{
                        $res = Cart::where('id', '=', $exist['id'])->update(array(
                            'qty' => (int)$data['qty']
                        ));
                    }
                }
                $this->response['data'] = $res;
                $this->response['error'] = null;
            }else{
                if(isset($data['reservation_code'])){
                    $reservation = app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('reservation_code', $data['reservation_code'], ['id', 'status']);
                    $data['reservation_id'] = $reservation[0]['id'];
                    $data['status'] = $reservation[0]['status'];
                }
                $res = Cart::create($data);
                $this->response['data'] = $res;
                $this->response['error'] = null;
            }
            $this->response['error'] = null;
            return $this->response();
        }
    }

    public function countById($priceId, $categoryId){
        // dd($priceId, $categoryId);
        $result = Cart::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where('deleted_at', '=', null)->where(function($query){
            $query->where('status', '=', 'confirmed')
                ->orWhere('status', '=', 'for_approval');
        })->sum('qty');
        return $result;
    }

    public function countReservationId($reservation_id){
        // dd($priceId, $categoryId);
        $result = Cart::where('price_id', '=', $reservation_id)->where('deleted_at', '=', null)->where(function($query){
            $query->where('status', '=', 'confirmed');
        })->where('status', '=', 'for_approval')->get([DB::raw('SUM(qty) as totalRooms'), 'check_in', 'check_out']);
        
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
                ->where('deleted_at', '=', null)
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'carts.status', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }else{
            $result = Cart::where('carts.account_id', '=', $data['account_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })
                ->where('deleted_at', '=', null)
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'carts.status', 'price_id', 'reservation_id',  'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }
        $reserve = [];
        $reservation = [];
        $coupon = null;
        if(sizeof($result) > 0 ){
            $reserve['total'] = null;
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $reservation =app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('id', $item['reservation_id'], ['code', 'reservation_code', 'details', 'coupon_id']);
                if(sizeof($reservation) > 0){
                    $coupon = app('App\Http\Controllers\CouponController')->retrieveById($reservation[0]['coupon_id']);
                    $result[$i]['coupon'] = $coupon;
                    $start = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in']);
                    $end = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out']);
                    $nightsDays = $end->diffInDays($start);
                    $result[$i]['reservation_details'] = $reservation;
                    $result[$i]['code'] = sizeOf($reservation) > 0 ? $reservation[0]['code'] : null;
                    $result[$i]['reservation_code'] = sizeOf($reservation) > 0 ? $reservation[0]['reservation_code'] : null;
                    $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
                    if($result[$i]['rooms'][0]['label'] === 'MONTH'){
                        $nightsDays = $end->diffInMonths($start);
                    }
                    $result[$i]['price_per_qty'] = $result[$i]['rooms'][0]['tax_price'] * $item['checkoutQty'];
                    $result[$i]['price_with_number_of_days'] = number_format(($result[$i]['price_per_qty'] * $nightsDays), '2', '.', '');
                    $reserve['total'] = (double)$reserve['total'] + (double)$result[$i]['price_with_number_of_days'];
                    $reserve['subTotal'] = $reserve['total'];

                    $details = json_decode($reservation[0]['details'], true);
                    if(sizeof($details['selectedAddOn']) > 0){
                        for ($a=0; $a <= sizeof($details['selectedAddOn'])-1 ; $a++) {
                            $each = $details['selectedAddOn'][$a];
                            $reserve['total'] = (float)$reserve['total'] + (float)$each['price'];
                            $reserve['subTotal'] = $reserve['total'];
                            $details['selectedAddOn'][$a]['price'] = number_format($each['price'], 2, '.', '');
                        }   
                    }
                    $reservation[0]['details'] = $details;
                }
            }
            if(sizeof($reservation) > 0){
                if($reservation[0]['coupon_id'] !== null){
                    if($coupon['type'] === 'fixed'){
                        $reserve['total'] = number_format((float)((double)$reserve['total'] - (double)$coupon['amount']), 2, '.', '');
                    }else if($coupon['type'] === 'percentage'){
                        $reserve['total'] = number_format((float)((double)$reserve['total'] - ((double)$coupon['amount'] / 100)), 2, '.', '');
                    }
                }else{
                    $reserve['total'] = number_format($reserve['total'], 2, '.', '');
                    $reserve['subTotal'] = number_format($reserve['subTotal'], 2, '.', '');
                }
            }
        }
        $this->response['data']['result'] = $result;
        $this->response['data']['total'] = $reserve;
        return $this->response();
    }

    public function retrieveCartWithRooms($reservation_id){
        $result = Cart::where('reservation_id', '=', $reservation_id)
            ->groupBy('carts.price_id')
            ->get(['qty', 'reservation_id', 'price_id', 'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) {
                $temp = [];
                $item = $result[$i];
                $rooms = app('Increment\Hotel\Room\Http\RoomController')->getWithQty($item['category_id'], $item['price_id']);
                $result[$i]['rooms'] = $rooms;
                $result[$i]['specificRooms'] = app('Increment\Hotel\Room\Http\RoomController')->retrieveByFilter($item['price_id'], $item['category_id']);
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
            ->select('qty', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty'))->first();
        if($result !== null){
            $refundable = 0;
            $nonRefundable = 0;
            $roomDetails = app('Increment\Hotel\Room\Http\RoomController')->getRoomDetails($result['category_id'], $result['price_id'], $result['qty']);
            if(sizeof($roomDetails) > 0){
                for ($i=0; $i <= sizeof($roomDetails)-1 ; $i++) { 
                  $item = $roomDetails[$i];
                  if($item['refundable'] !== null){
                      $refundable ++;
                  }else{
                      $nonRefundable ++;
                  }
                }
            }
            $result['refundable'] = $refundable;
            $result['non_refundable'] = $nonRefundable;
            $result['rooms'] = $roomDetails;
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
        return Cart::where('reservation_id', '=', $reservationId)->groupBy('price_id')->first();
    }

    public function getTotalReservations($priceId, $categoryId){
        return Cart::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->where(function($query){
            $query->where('status', '=', 'confirmed')
            ->orWhere('status', '=', 'for_approval');
        })->where('deleted_at', '=', null)->sum('qty');
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
                    ->orWhere('status', '=', 'in_progress');
                }
            ));
        }
        if(isset($params['reservation_id'])){
            $whereArray = array();
            array_push($whereArray, array(
                'reservation_id', '=', $params['reservation_id']
            ));
        }
        if($params['method'] === 'update'){
            $result = Cart::where($whereArray)
            ->groupBy('carts.price_id')
            ->get(['id', 'qty', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }else{
            $result = Cart::where($whereArray)
            ->groupBy('carts.price_id')
            ->get(['id', 'qty', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
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

    public function getByDate(Request $request){
        $data = $request->all();
        $result = Cart::whereDate('check_in', '=', $data['check_in'])->whereDate('check_out', '=', $data['check_out'])->where('deleted_at', '=', null)->get();
        $this->response['data'] = $result;
        return $this->response();
    }

    public function getTotalBookings($date){
        $status = array(
            array('status', '=', 'confirmed'),
            array('status', '=', 'for_approval'),
            array('status', '=', 'completed'),
        );
		$bookings = Cart::where($status)->where('check_in', '<=', $date)->where('deleted_at', '=', null)->count();
		$reservations = Cart::where($status)->where('check_in', '>', $date)->where('deleted_at', '=', null)->count();
		return array(
			'previous' => $bookings,
			'upcommings' => $reservations
		);
	}

    public function getByCategory($category){
        return Cart::where('category_id', '=', $category)->where(function($query){
            $query->where('status', '=', 'for_approval')
            ->orWhere('status', '=', 'confirmed');
        })->get();
    }

    public function retrieveByPriceId($priceId){
        return Cart::where('price_id', '=', $priceId)->where(function($query){
            $query->where('status', '=', 'for_approval')
            ->orWhere('status', '=', 'confirmed');
        })->where('deleted_at', '=', null)->get();
    }
}
