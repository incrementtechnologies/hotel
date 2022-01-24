<?php

namespace Increment\Hotel\Reservation\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use App\TopChoice;
use Increment\Hotel\Reservation\Models\Reservation;
use Increment\Hotel\Reservation\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ReservationController extends APIController
{

	public $synqtClass = 'App\Http\Controllers\SynqtController';
	public $merchantClass = 'Increment\Account\Merchant\Http\MerchantController';
	public $messengerGroupClass = 'Increment\Messenger\Http\MessengerGroupController';
	public $ratingClass = 'Increment\Common\Rating\Http\RatingController';
	public $topChoiceClass = 'App\Http\Controllers\TopChoiceController';
	public $roomController = 'App\Http\Controllers\RoomController';
	public $locationClass = 'Increment\Imarket\Location\Http\LocationController';
	public $emailClass = 'App\Http\Controllers\EmailController';
	public $temp = array();

	function __construct()
	{
		$this->model = new Reservation();
		$this->model = new Booking();
		$this->notRequired = array(
			'code', 'coupon_id', 'payload', 'payload_value', 'total', 'merchant_id'
		);
	}

	public function retrieveAllDetails(Request $request){
		$data = $request->all();
		$reserve = Reservation::where('code', '=', $data['id'])->first();
		$cart = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRooms($reserve['id']);
		if(sizeof($cart) > 0){
			$reserve['details'] = json_decode($reserve['details'], true);
			$reserve['account_info'] = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($reserve['account_id'], ['first_name as name', 'cellular_number as contactNumber']);
			$reserve['account_info']['email'] = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($reserve['account_id'], ['email'])['email'];
			$reserve['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $reserve['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$reserve['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $reserve['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$reserve['coupon'] = $reserve['coupon_id'] !== null ? app('App\Http\Controllers\CouponController')->retrieveById($reserve['coupon_id']) : array('code' => null);
			$array = array(
				'reservation' => $reserve,
				'cart' => $cart,
				'customer' => $this->retrieveAccountDetails($reserve['account_id']),
			);
			$this->response['data'] = $array;
		}else{
			$this->response['data'] = [];
		}
		return $this->response();
	}

	public function create(Request $request)
	{
		$data = $request->all();
		$data['account_info'] = json_decode($data['account_info']);
		$customerInfo = array(
			'account_id' => $data['account_id'],
			'first_name' => $data['account_info']->name,
			'cellular_number' => $data['account_info']->contactNumber
		);
		app('Increment\Account\Http\AccountInformationController')->createByParams($customerInfo);
		$this->model = new Reservation();
		$temp = Reservation::count();
		$data['code'] = $this->generateCode($temp);
		$this->insertDB($data);
		if($this->response['data']){
			$condition = array(
				array('account_id', '=', $data['account_id']),
				array('reservation_id', '=', null),
				array('deleted_at', '=', null),
				array(function($query){
					$query->where('status', '=', 'pending')
					->orWhere('status', '=', 'in_progress');
				})
			);
			$updates = array(
				'status' => 'in_progress',
				'reservation_id' => $this->response['data'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		}
		return $this->response();
	}

	public function update(Request $request){
		$data = $request->all();
		$this->model = new Reservation();
		$confirmed = Reservation::where('id', '=', $data['id'])->first();
		if($confirmed['status'] === 'confirm'){
			$this->response['data'] = null;
			$this->response['error'] = 'Your reservation has been confirmed by the admin';
			return $this->response();;
		}else{
			$data['account_info'] = json_decode($data['account_info']);
			$accountInfo = array(
				'first_name' => $data['account_info']->name,
				'cellular_number' => $data['account_info']->contactNumber
			);
			app('Increment\Account\Http\AccountInformationController')->updateByAccountId($data['account_id'], $accountInfo);
			$cart = json_decode($data['carts']);
			for ($i=0; $i <= sizeof($cart)-1 ; $i++) { 
				$item = $cart[$i];
				$condition = array(
					array('account_id', '=', $data['account_id']),
					array('category_id', '=', $item->category),
					array('deleted_at', '=', null),
					array(function($query){
						$query->where('status', '=', 'in_progress')
						->orWhere('status', '=', 'pending')
						->orWhere('status', '=', 'in_progress');
					})
				);
				$updates = array(
					'status' => 'in_progress',
					'qty' => $item->checkoutQty,
					'reservation_id' => $data['id'],
					'updated_at' => Carbon::now()
				);
				app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			}
			$update = Reservation::where('id', '=', $data['id'])->update(array(
				'details' => $data['details'],
				'check_in' => $data['check_in'],
				'check_out' => $data['check_out'],
			));
			$this->response['data'] = $update;
			return $this->response();
		}
	}

	public function updateCoupon(Request $request){
		$data = $request->all();
		$reserve = Reservation::where('account_id', '=', $data['account_id'])->where('id', '=', $data['id'])->first();
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where('account_id', '=', $data['account_id'])->where('id', '=', $data['id'])->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status'],
				'total' => $data['amount']
			));
			$condition = array(
				array('reservation_id', '=', $data['id']),
				array('account_id', '=', $data['account_id'])
			);
			$updates = array(
				'status' => $data['status'],
				'updated_at' => Carbon::now()
			);
			$updateCart = app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			if($updateCart){
				$cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($reserve['id']);
				$priceStatusParams = array(
					'price_id' => $cart['price_id'],
					'category_id' => $cart['category_id']
				);
				$existingPriceStatus = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->checkIfPriceExist($priceStatusParams);
				if(sizeof($existingPriceStatus) > 0){
					$roomPriceUpdate = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateQtyByPriceId($cart['price_id'], $cart['category_id'], ((int)$existingPriceStatus[0]['qty'] + $cart['qty']));
				}else{
					$roomPriceUpdate = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateQtyByPriceId($cart['price_id'], $cart['category_id'], 1);
				}
			}
			if($res !== null){
				$this->response['data'] = $reserve;
			}
		}
		return $this->response();
	}

	public function updateByParams($condition, $updates){
		return Reservation::where($condition)->update($updates);
	}

	public function updateReservationCart($data){
		$reserve = Reservation::where('id', '=', $data['id'])->first();
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where('id', '=', $data['id'])->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status']
			));
			$condition = array(
				array('reservation_id', '=', $data['id'])
			);
			$updates = array(
				'status' => $data['status'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			if($res !== null){
				return $reserve;
			}
		}
	}

	public function retrieveByParams($whereArray, $returns)
	{
		$result = Reservation::where($whereArray)->get($returns);
		return sizeof($result) > 0 ? $result[0] : null;
	}

	public function generateCode($counter)
	{
		$length = strlen((string)$counter);
    $code = '00000000';
    return 'MEZZO_'.substr_replace($code, $counter, intval(7 - $length));
	}

	public function retrieveBookings(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		$sortBy = 'reservations.'.array_keys($data['sort'])[0];
		$condition = array(
			array('reservations.' . $con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array(function($query){
				$query->where('reservations.status', '=', 'for_approval')
					->orWhere('reservations.status', '=', 'confirmed')
					->orWhere('reservations.status', '=', 'completed')
					->orWhere('reservations.status', '=', 'cancelled')
					->orWhere('reservations.status', '=', 'refunded');
			})
		);
		if ($con[0]['column'] == 'email') {
			$sortBy = 'T2.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T2.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
			);
		} else if ($con[0]['column'] == 'payload_value') {
			$sortBy = 'T3.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T3.title', $con[0]['clause'], $con[0]['value'])
			);
		}else if ($con[0]['column'] == 'price') {
			$sortBy = 'T5.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T5.regular', $con[0]['clause'], $con[0]['value'])
			);
		}
		if(sizeof($con) > 1){
			array_push($condition, 
				array($con[1]['column'], $con[1]['clause'], $con[1]['value'])
			);
		}
		$res = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->leftJoin('carts as T6', 'T6.reservation_id', '=', 'reservations.id')
			->where($condition)
			->where('T6.deleted_at', '=', null)
			->where('reservations.deleted_at', '=', null)
			->orderBy($sortBy, array_values($data['sort'])[0])
			->limit($data['limit'])
			->offset($data['offset'])
			->get(['reservations.*', 'T2.email', 'T3.title', 'T5.regular']);
		// dd($res);
		$size = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
		->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
		->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
		->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
		->leftJoin('carts as T6', 'T6.reservation_id', '=', 'reservations.id')
		->where($condition)
		->where('T6.deleted_at', '=', null)
		->orderBy($sortBy, array_values($data['sort'])[0])
		->get();
		
		for ($i=0; $i <= sizeof($res)-1; $i++) { 
			$item = $res[$i];
			$res[$i]['details'] = json_decode($item['details']);
			$res[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['name'] = $this->retrieveNameOnly($item['account']);
		}

		$this->response['size'] = sizeOf($size);
		$this->response['data'] = $res;
		return $this->response();
	}

	public function retrieveTotalPreviousBookings(){
		$currDate = Carbon::now()->toDateTimeString();
		$res = Reservation::where('status', '=', 'verified')->where('created_at', '<', $currDate)->count();

		return $res;
	}

	public function retrieveTotalUpcomingBookings(){
		$currDate = Carbon::now()->toDateTimeString();
		$res = Reservation::where('status', '=', 'verified')->where('check_in', '>=', $currDate)->count();
		return $res;
	}

	public function retrieveTotalReservationsByAccount($accountId){
		$res = Reservation::where('account_id', '=', $accountId)->count();
		return $res;
	}

	public function retrieveTotalSpentByAcccount($accountId){
		$res = Reservation::where('account_id', '=', $accountId)->sum('total');
		return $res;
	}

	public function getTotalBookings($date){
		$bookings = Reservation::where('check_in', '<=', $date)->where('deleted_at', '=', null)->count();
		$reservations = Reservation::where('check_in', '>', $date)->where('deleted_at', '=', null)->count();
		return array(
			'previous' => $bookings,
			'upcommings' => $reservations
		);
	}

	public function retrieveDetails(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
			->where(function($query){
				$query->where('status', '=', 'in_progress')
					->orWhere('status', '=', 'failed')
					->orWhere('status', '=', 'for_approval')
					->orWhere('status', '=', 'pending');
			})->where('details', 'not like', '%'.'"payment_method":"credit"'.'%')
			->get();
		// $rooms = [];
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result) -1; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['payload_value'] =  json_decode($item['payload_value']);;
			}
		}
		$carts = app('Increment\Hotel\Room\Http\CartController')->retrieveOwn($data);
		$accountInfo = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($con[0]['value'], ['first_name as name', 'cellular_number as contactNumber']);
		$accountInfo['email'] = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($con[0]['value'], ['email'])['email'];
		$this->response['data']['reservations'] = $result;
		$this->response['data']['carts'] = $carts;
		$this->response['data']['account_info'] = $accountInfo;
		return $this->response();
	}

	public function countByIds($accountId, $couponId){
		if($accountId === null && $couponId !== null){
			return Reservation::where('coupon_id', '=', $couponId)->count();
		}else if($accountId !== null && $couponId === null){
			return Reservation::where('account_id', '=', $accountId)->count();
		}else if($accountId !== null && $couponId !== null){
			return Reservation::where('account_id', '=', $accountId)->where('coupon_id', '=', $couponId)->count();
		}
	}

	public function updateByCouponCode($couponId, $id){
		return Reservation::where('id', '=', $id)->update(array(
			'coupon_id' => $couponId
		));
	}

	public function updatedCoupon(Request $request){
		$data = $request->all();
		$res = Reservation::where('id', '=', $data['id'])->update(array(
			'coupon_id' => null,
			'updated_at' => Carbon::now()
		));
		$this->response['data'] = $res;
		return $this->response();
	}

	public function getByIds($accountId, $status){
		return Reservation::where('account_id', '=', $accountId)->where('status', '=', $status)->first();
	}

	public function updateReservations(Request $request){
		$data = $request->all();
		$reservation = Reservation::where('code', '=', $data['roomCode'])->first();
		$res = Reservation::where('code', '=', $data['roomCode'])->update(array(
			'status' => $data['status']
		));
		$condition = array(
			array('reservation_id', '=', $reservation['id'])
		);
		$updates = array(
			'status' => $data['status'],
			'updated_at' => Carbon::now()
		);
		app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		if(isset($data['booking'])){
			if(sizeof($data['booking']) > 0){
				for ($i=0; $i <= sizeof($data['booking'])-1; $i++) {
					$item = $data['booking'][$i];
					$params = array(
						'reservation_id' =>  $data['reservation_id'],
						'room_id' => $item['room_id'], 
						'room_type_id' => $item['category']
					);
					Booking::create($params);
				}
			}
		}
		$this->response['data'] = $res;
		return $this->response();
	}

	public function retrieveBookingsByParams($column, $value){
		return Booking::where($column, '=', $value)->get();
	}

	public function retrieveReservationByParams($column, $value, $return){
		return Reservation::where($column, '=', $value)->get($return);
	}

	public function retrieveSaleByCoupon($column, $value){
		if($column !== null && $value !== null){
			$result = Reservation::where($column, '=', $value)->select(DB::raw('COUNT(coupon_id) as total_booking'), DB::raw('SUM(total) as total'))->get();
		}else if($column === null && $value === null){
			$result = Reservation::select(DB::raw('COUNT(coupon_id) as total_booking'), DB::raw('SUM(total) as total'))->get();
		}
		return $result;
	}

	public function retrieveMyBookings(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$whereArray = array(
			array('reservations.'.$con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array('reservations.'.$con[1]['column'], $con[1]['clause'], $con[1]['value']),
			array('reservations.'.$con[2]['column'], $con[2]['clause'], $con[2]['value']),
			array('reservations.'.$con[3]['column'], $con[3]['clause'], $con[3]['value'])
		);
		$result = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->leftJoin('pricings as T2', 'T2.id', '=', 'T1.price_id')
			->where($whereArray)
			->where('reservations.account_id', '=', $data['account_id'])
			->groupBy('T1.reservation_id')
			->limit($data['limit'])
			->offset($data['offset'])->get(['reservations.*', 'T2.regular', 'T2.refundable', 'T2.currency', 'T2.label']);
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F d, Y');
        $result[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F d, Y');
				$result[$i]['rooms'] = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRoomDetails($item['id']);
			}
		}
		$this->response['data'] = $result;
		return $this->response();
	}

	public function checkout(Request $request){
		$data = $request->all();
		$reservation = Reservation::where('account_id', '=', $data['account_id'])->where('code', '=', $data['reservation_code'])->first();
		if($reservation !== null){
			$accountEmail = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($data['account_id'], ['email']);
			$accountInformation = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($data['account_id'], ['cellular_number', 'first_name']);
			$details = json_decode($reservation['details']);
			$details->payment_method = $data['payment_method'];
			$details = json_decode($reservation['details']);
			$params = array(
				"account_id" => $data['account_id'],
				"amount" => $data['amount'],
				"name" => $accountInformation->first_name,
				"email" => $accountEmail['email'],
				"referenceNumber" => $reservation['code'],
				"contact_number" => $accountInformation->cellular_number,
				"payload" => "reservation",
				"payload_value" => $reservation['id'],
				"successUrl" => $data['success_url'],
				"failUrl" => $data['failure_url'],
				"cancelUrl" => $data['cancel_url']
			);
			$res = app('Increment\Hotel\Payment\Http\PaymentController')->checkout($params);
			if($res['data'] !== null){
				Reservation::where('code', '=', $data['reservation_code'])->update(array(
					'total' => $data['amount'],
					'details' => json_encode($details),
					'status' => 'for_approval'
				));
				$condition = array(
					array('reservation_id', '=', $reservation['id']),
					array('account_id', '=', $data['account_id'])
				);
				$updates = array(
					'status' => 'for_approval',
					'updated_at' => Carbon::now()
				);
				app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
				$this->response['data'] = $res['data'];
			}else{
				$this->response['data'] = $res['error'];
			}
			$cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($reservation['id']);
			$priceStatusParams = array(
				'price_id' => $cart['price_id'],
				'category_id' => $cart['category_id']
			);
			$existingPriceStatus = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->checkIfPriceExist($priceStatusParams);
			if(sizeof($existingPriceStatus) > 0){
				$roomPriceUpdate = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateQtyByPriceId($cart['price_id'], $cart['category_id'], ((int)$existingPriceStatus[0]['qty'] + 1));
			}else{
				$roomPriceUpdate = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateQtyByPriceId($cart['price_id'], $cart['category_id'], 1);
			}
		}
		return $this->response();
	}

	public function retrieveDashboard(Request $request){
		$data = $request->all();
		$currDate = Carbon::now();
		$month = Carbon::now()->subDays(30);
		$diffinWeeks = Carbon::now()->diffinWeeks(Carbon::now()->subDays(30));
		$carbon = Carbon::now()->subDays(30);
		$dates = [];
		$result = [];
		$i=0;
		$dateList = CarbonPeriod::create($carbon->toDateTimeString(), $currDate->toDateTimeString());
		foreach ($dateList as $date) {
			array_push($dates, $date->toDateString());
		}
		foreach ($dates as $key) {
			$reservations = Reservation::where('created_at', 'like', '%'.$key.'%')->count();
			$sales = Reservation::where('created_at', 'like', '%'.$key.'%')->sum('total');
			array_push($result, array(
				'date' => $key,
				'total_reservations' => $reservations,
				'total_sales' => $sales
			));
		}
		$this->response['data'] = $result;
		return $this->response();
	// 	while ($carbon < $currDate){
	// 		$dates[$carbon->weekOfMonth][$i] = $carbon->toDateString();
	// 		$carbon->addDay();
	// 		$i++;
	// 	}
	// 	foreach ($dates as $key) {
	// 		$reservations = Reservation::whereBetween('created_at', [$key[array_key_first($key)], end($key)])->count();
	// 		$sales = Reservation::whereBetween('created_at', [$key[array_key_first($key)], end($key)])->sum('total');
	// 		array_push($result, array(
	// 			'date' => end($key),
	// 			'total_reservations' => $reservations,
	// 			'total_sales' => $sales
	// 		));
	// 	}
	// 	$this->response['data'] = $result;
	// 	return $this->response();
	}
	
	public function successCallback(Request $request){
		$data = $request->all();
		
		header('Location: '.env('FRONT_URL_SUCCESS').'?code='.$data['code']);
		exit(1);
	}

	public function failCallback(Request $request){
		$data = $request->all();
		header('Location: '.env('FRONT_URL_FAIL').'?code='.$data['code']);
		exit(1);
	}

	public function retrieveBooking(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		$sortBy = 'reservations.'.array_keys($data['sort'])[0];
		$condition = array(
			array('reservations.' . $con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array(function($query){
				$query->where('reservations.status', '=', 'for_approval')
					->orWhere('reservations.status', '=', 'confirmed')
					->orWhere('reservations.status', '=', 'completed')
					->orWhere('reservations.status', '=', 'cancelled')
					->orWhere('reservations.status', '=', 'refunded');
			})
		);
		if ($con[0]['column'] == 'email') {
			$sortBy = 'T2.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T2.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
			);
		} else if ($con[0]['column'] == 'payload_value') {
			$sortBy = 'T3.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T3.title', $con[0]['clause'], $con[0]['value'])
			);
		}else if ($con[0]['column'] == 'price') {
			$sortBy = 'T5.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T5.regular', $con[0]['clause'], $con[0]['value'])
			);
		}
		if(sizeof($con) > 1){
			array_push($condition, 
				array($con[1]['column'], $con[1]['clause'], $con[1]['value'])
			);
		}

		$res = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('bookings as T3', 'T3.reservation_id', 'reservations.id')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->leftJoin('carts as T6', 'T6.reservation_id', '=', 'reservations.id')
			->where($condition)
			->where('T6.deleted_at', '=', null)
			->where('reservations.deleted_at', '=', null)
			->orderBy($sortBy, array_values($data['sort'])[0])
			->limit($data['limit'])
			->offset($data['offset'])
			->get(['reservations.*', 'T2.email', 'T3.room_id', 'T5.regular']);
		// dd($res);
		$size = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
		->leftJoin('bookings as T3', 'T3.reservation_id', 'reservations.id')
		->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
		->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
		->leftJoin('carts as T6', 'T6.reservation_id', '=', 'reservations.id')
		->where($condition)
		->where('T6.deleted_at', '=', null)
		->orderBy($sortBy, array_values($data['sort'])[0])
		->get();
		
		for ($i=0; $i <= sizeof($res)-1; $i++) { 
			$item = $res[$i];
			$res[$i]['details'] = json_decode($item['details']);
			$res[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['name'] = $this->retrieveNameOnly($item['account']);
			$res[$i]['room'] = app('Increment\Hotel\Room\Http\RoomController')->retrieveByIDParams($item['room_id']);
		}

		$this->response['size'] = sizeOf($size);
		$this->response['data'] = $res;
		return $this->response();
	}
}
