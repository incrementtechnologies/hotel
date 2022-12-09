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
        if((int)$data['account_id'] == 0){
            $createdAccount = app('Increment\Account\Http\AccountController')->retrieveByEmail($data['email']);
			if($createdAccount !== null){
				$data['account_id'] = $createdAccount['id'];
			}
        }
        $emptyCart = Cart::where('account_id', '=', $data['account_id'])->where('status', '!=', 'completed')->where('deleted_at', '=', null)->get();
        if(isset($data['reservation_code'])){
            $getReservation = app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('reservation_code', $data['reservation_code'], ['id', 'status']);
            if(sizeof($getReservation) > 0){
                $existingCart = Cart::where('reservation_id', '=', $getReservation[0]['id'])
                    ->where('account_id', '=', $data['account_id'])
                    ->where('status', '=', 'for_approval')
                    ->where('check_in', 'not like', '%'.$data['check_in'].'%')
                    ->where('check_out', 'not like', '%'.$data['check_out'].'%')->get();
                if(sizeof($existingCart) <= 0){
                    $createdCart = Cart::where('reservation_id', '=', $getReservation[0]['id'])->first();
                    if(isset($data['reservation_code'])){
                        if($createdCart !== null){
                            $this->model = new Cart();
                            $cartParams = array(
                                'account_id' => $data['account_id'],
                                'price_id' => $data['price_id'],
                                'category_id' => $data['category_id'],
                                'reservation_id' => $getReservation[0]['id'],
                                'qty' => $data['qty'],
                                'status' => 'in_progress',
                                'details' => $data['details'],
                                'check_in' => $createdCart['check_in'],
                                'check_out' => $createdCart['check_out']
                            );
                            $res = Cart::create($cartParams);
                            $this->response['data'] = $res;
                            $this->response['error'] = null;
                            return $this->response();
                        }
                    }else{
                        $this->response['data'] = [];
                        $this->response['error'] = 'You had previously added rooms with this email with different dates in your cart. Kindly remove or checkout these rooms to proceed';
                        return $this->response();
                    }
                }else{
                    $this->response['data'] = [];
                    $this->response['error'] = 'You had previously added rooms with this email with different dates in your cart. Kindly remove or checkout these rooms to proceed';
                    return $this->response();
                }
            }
        }
        else{
            $existingCart = Cart::where('account_id', '=', $data['account_id'])
                ->where(function($query)use($data){
                    $query->where('check_in', '!=', $data['check_in'])
                    ->orWhere('check_out', '!=', $data['check_out']);
                })
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })->where('deleted_at', '=', null)
                ->first();
            $totalAddedByDate = Cart::where('account_id', '=', $data['account_id'])
                ->where('check_in', '=', $data['check_in'])
                ->where('category_id', '=', $data['category_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })->where('deleted_at', '=', null)
                ->sum('qty');
            $cartDetails = json_decode($data['details'], true);
            $availability = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByIds($data['category_id'], $data['check_in'], $cartDetails['add-on']);
            if($existingCart != null && sizeof($emptyCart) > 0 && (isset($data['override']) && ($data['override'] == 'false' || $data['override'] == false))){
                $this->response['data'] = [];
                $this->response['error'] = 'You had previously added rooms with this email with different dates in your cart. Kindly remove or checkout these rooms to proceed';
                return $this->response();
            }else if(((int)$availability['limit_per_day'] - (int)$totalAddedByDate) <= 0){
                $this->response['error']  = "One of your room already reached it's available slot for this date!";
                $this->response['data'] = [];
                return $this->response();
            }else{
                if(isset($data['reservation_code'])){
                    $reservation = app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('reservation_code', $data['reservation_code'], ['id', 'status']);
                    $data['reservation_id'] = $reservation[0]['id'];
                    $data['status'] = $reservation[0]['status'];
                }
                if($existingCart != null && sizeof($emptyCart) > 0 && (isset($data['override']) && ($data['override'] == 'true' || $data['override'] == true))){
                    for ($i=0; $i <= sizeof($emptyCart)-1; $i++) { 
                        $eCart = $emptyCart[$i];
                        Cart::where('id', '=', $eCart['id'])->update(array('deleted_at' => Carbon::now()));
                    }
                }
                $cartExisted = Cart::where('category_id', '=', $data['category_id'])
                    ->where('details', 'like', '%'.$data['details'].'%')
                    ->where('check_in', '=', $data['check_in'])
                    ->where('check_out', '=', $data['check_out'])
                    ->where(function($query){
                        $query->where('status', '=', 'pending')
                        ->orWhere('status', '=', 'in_progress');
                    })->where('deleted_at', '=', null)
                    ->first();
                if($cartExisted != null && (isset($data['override']) && ($data['override'] == 'false' || $data['override'] == false))){
                    $res = Cart::where('id', '=', $cartExisted['id'])->update(array(
                        'qty' => (int)$cartExisted['qty'] + (int)$data['qty'],
                        'updated_at' => Carbon::now(),
                    ));
                    $this->response['data'] = $res;
                    $this->response['error'] = null;
                    return $this->response();
                }else{
                    $res = Cart::create($data);
                    $this->response['data'] = $res;
                    $this->response['error'] = null;
                    return $this->response();
                }
            }
        }
    }

    public function updateCreate(Request $request){
        $data = $request->all();
        $removeExisting = Cart::where('id', '=', $data['id'])->update(array('deleted_at' => Carbon::now()));
        if($removeExisting){
            unset($data['id']);
            $this->model = new Cart();
            $this->insertDB($data);
        }
        return $this->response();
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
        })->where('status', '=', 'for_approval')->get([DB::raw('SUM(qty) as totalRooms'), 'check_in', 'check_out', 'category_id']);
        
        return $result;
    }

    public function countByCategory($category){
        $result = Cart::where('category_id', '=', $category)->where(function($query){
            $query->where('status', '=', 'comfirmed')
            ->orWhere('status', '=', 'for_approval');
        })->where('deleted_at', '=', null)->count();
        
        return $result;
    }
    
    public function retrieveByParams(Request $request){
        $data = $request->all();
        if(isset($data['reservation_code'])){
            $reservationId = Reservation::where('reservation_code', '=', $data['reservation_code'])->get(['id']);
            $result = Cart::where('carts.account_id', '=', $data['account_id'])
                ->where('reservation_id', '=', $reservationId[0]['id'])
                ->where('deleted_at', '=', null)
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'details', 'carts.status', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }else{
            $result = Cart::where('carts.account_id', '=', $data['account_id'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                    ->orWhere('status', '=', 'in_progress');
                })
                ->where('deleted_at', '=', null)
                ->groupBy('carts.price_id')
                ->get(['id', 'qty', 'details', 'carts.status', 'price_id', 'reservation_id',  'check_in', 'check_out', 'category_id', DB::raw('Sum(qty) as checkoutQty')]);
        }
        $reserve = [];
        $reservation = [];
        $coupon = null;
        if(sizeof($result) > 0 ){
            $reserve['total'] = null;
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $cartDetails = json_decode($item['details'], true);
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
                    $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomTypeController')->getDetails($item['category_id'], $item['details']);
                    // if($result[$i]['rooms'][0]['label'] === 'MONTH'){
                    //     $nightsDays = $end->diffInMonths($start);
                    // }
                    $result[$i]['price_per_qty'] = (float)$cartDetails['room_price'] * $item['checkoutQty'];
                    $result[$i]['price_with_number_of_days'] = ($result[$i]['price_per_qty'] * $nightsDays);
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
                        $reserve['total'] = number_format((float)((double)$reserve['total'] - ((double)$reserve['total'] * ((double)$coupon['amount'] / 100))), 2, '.', '');
                    }
                }else{
                    $reserve['total'] = number_format($reserve['total'], 2, '.', '');
                    $reserve['subTotal'] = number_format($reserve['subTotal'], 2, '.', '');
                }
            }
            $otherDetails = [
                'customer_details' => app('Increment\Account\Http\AccountController')->retrieveAccountInfo($data['account_id']),
                'reservation_detail' => app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('id', $result[0]['reservation_id'], ['details']),
            ];
            $this->response['data']['other_details'] = $otherDetails;
        }
        $this->response['data']['result'] = $result;
        $this->response['data']['total'] = $reserve;
        return $this->response();
    }

    public function retrieveCartWithRooms($reservation_id){
        $result = Cart::where('reservation_id', '=', $reservation_id)
            // ->groupBy('carts.price_id')
            ->get(['qty', 'details', 'reservation_id', 'price_id', 'check_in', 'check_out', 'category_id', 'qty as checkoutQty']);
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) {
                $temp = [];
                $item = $result[$i];
                $rooms = app('Increment\Hotel\Room\Http\RoomTypeController')->getDetails($item['category_id'], $item['details']);
                $result[$i]['rooms'] = $rooms;
                if($rooms !== null){
                    $booking = app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveBookingsByParams('reservation_id',  $item['reservation_id']);
                    if(sizeof($booking) > 0){
                        for ($a=0; $a <= sizeof($booking)-1; $a++) { 
                            $each = $booking[$a];
                            array_push($temp, array(
                                'room_id' => $each['room_id'],
                                'category' => $each['room_type_id']
                            ));
                        }
                    }else{
                        for ($a=0; $a <= (int)$item['qty']-1 ; $a++) { 
                            array_push($temp, array(
                                'category' => null,
                                'room_id' => null,
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
            ->select('qty', 'price_id', 'category_id', DB::raw('Sum(qty) as checkoutQty'))->first();
        if($result !== null){
            $refundable = 0;
            $nonRefundable = 0;
            $roomDetails = app('Increment\Hotel\Room\Http\RoomController')->getRoomDetails($result['category_id'], $result['price_id'], $result['checkoutQty']);
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
        $result = [];
        $account_id = $params['condition'][0]['value'];
        $checkoutQty = 0; 
        $whereArray = array(
            array('account_id', '=', $account_id),
            array('deleted_at', '=', null)
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
            ->select('id', 'details', 'qty', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id')
            ->get();
            // $checkoutQty = Cart::where($whereArray)->sum('qty');
        }else{
            $result = Cart::where($whereArray)
            ->select('id', 'details', 'qty', 'price_id', 'reservation_id', 'check_in', 'check_out', 'category_id')
            ->get();
            // $checkoutQty = Cart::where($whereArray)->sum('qty');
        }
        $final = [];
        if(sizeof($result) > 0 ){
            for ($i=0; $i <= sizeof($result) -1; $i++) { 
                $item = $result[$i];
                $addOn = json_decode($item['details'], true);
                $reservation =app('Increment\Hotel\Reservation\Http\ReservationController')->retrieveReservationByParams('id', $item['reservation_id'], ['code']);
                $availabilty = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByIds($item['category_id'], $item['check_in'], $addOn['add-on']);
                $result[$i]['reservation_code'] = sizeOf($reservation) > 0 ? $reservation[0]['code'] : null;
                $result[$i]['checkoutQty'] = $item['qty']; //$checkoutQty;
                $result[$i]['rooms'] = app('Increment\Hotel\Room\Http\RoomTypeController')->getDetails($item['category_id'], $item['details']);
                $result[$i]['limit_per_day'] = $availabilty['limit_per_day'];
                $exist = array_filter($final, function($each)use($item){
                    return $each['category_id'] == $item['category_id'] && $each['rooms']['add-on'] == $item['rooms']['add-on'] && $each['rooms']['room_price'] == $item['rooms']['room_price'];
                });
                if(sizeof($exist) <= 0){
                    array_push($final, $result[$i]);
                }
            }
        }
        return $final;
    }

    public function getByDate(Request $request){
        $data = $request->all();
        $result = Cart::whereDate('check_in', '=', $data['check_in'])->whereDate('check_out', '=', $data['check_out'])->where('deleted_at', '=', null)->get();
        $this->response['data'] = $result;
        return $this->response();
    }

    public function getTotalBookings($date){
        $status = array(
            array(function($query){
                $query->where('status', '=', 'confirmed')
                ->orWhere('status', '=', 'for_approval')
                ->orWhere('status', '=', 'completed');
            })
        );
        if($date !== null){
            $bookings = Cart::where($status)->where('check_in', '<=', $date)->where('deleted_at', '=', null)->count();
            $reservations = Cart::where($status)->where('check_in', '>', $date)->where('deleted_at', '=', null)->count();
            return array(
                'previous' => $bookings,
                'upcommings' => $reservations
            );
        }else{
            $bookings = Cart::where($status)->where('deleted_at', '=', null)->count();
            return array(
                'total' => $bookings
            );
        }
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

    public function getMaxMinDates(){
        $max = Cart::orderBy('check_in', 'asc')->first();
        $min = Cart::orderBy('check_in', 'desc')->first();

        return array(
            'max' => $max !== null ? $max['check_in'] : null,
            'min' => $max !== null ? $min['check_in'] : null
        );
    }
    public function getTotalBookingsPerMonth($start, $end){
        $status = array(
            array(function($query){
                $query->where('status', '=', 'confirmed')
                ->orWhere('status', '=', 'for_approval')
                ->orWhere('status', '=', 'completed');
            })
        );
        return Cart::where($status)->whereBetween('check_in', [$start, $end])->count();
    }

    public function retrieveAllByReservationId($reservationId){
        return Cart::where('reservation_id', '=', $reservationId)->get();
    }

    public function countDailyCarts($startDate, $addOn, $category){
        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $result = Cart::where('category_id', '=', $category)->where(function($query){
            $query->where('status', '=', 'comfirmed')
            ->orWhere('status', '=', 'for_approval');
        })->where('check_in', 'like', '%'.$startDate.'%')
        ->where('details', 'like', '%'.$addOn.'%')
        ->where('deleted_at', '=', null)->sum('qty');
        return $result;
    }

    public function getOwnCarts($data){
        return Cart::where('account_id', '=', $data['account_id'])->where(function($query){
            $query->where('status', '=', 'pending')
            ->orWhere('status', '=', 'inprogress');
        })->get();
    }

    public function getCartsWithCount($reservationId, $status=null){
        $temp = [];
        if($status == null){
            $temp = Cart::where('reservation_id', '=', $reservationId)->get();
        }else{
            $temp = Cart::where('reservation_id', '=', $reservationId)->where('status', '=', $status)->get();
        }
        $breakfastOnly = 0;
        $roomOnly = 0;
        $both = 0;
        $returnArray = array(); 
        if(sizeof($temp) > 0){
            for ($i=0; $i <= sizeof($temp)-1 ; $i++) { 
                $item = $temp[$i];
                $addOn = json_decode($item['details'], true);
                $cartDetails = json_decode($item['details'], true);
                // $roomDetails = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByIds($item['category_id'], $item['check_in'], $addOn['add-on']);
                $roomType = app('Increment\Hotel\Room\Http\RoomTypeController')->getById($item['category_id']);
                if($cartDetails['add-on'] == 'With Breakfast'){
                    $returnArray[] = array(
                        'qty' => $item['qty'],
                        'room_type' => $roomType['payload_value'],
                        'key' => 'with breakfast'
                    );
                }
                if($cartDetails['add-on'] == 'Room Only'){
                    $returnArray[] = array(
                        'qty' => $item['qty'],
                        'room_type' => $roomType['payload_value'],
                        'key' => 'room only'
                    );
                }
            }
        }
        return $returnArray;
    }
}
