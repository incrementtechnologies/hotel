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
use Illuminate\Support\Facades\Cache;

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
		$reserve = Reservation::where('reservation_code', '=', $data['id'])->first();
		$cart = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRooms($reserve['id']);
		if(sizeof($cart) > 0){
			$reserve['total'] = null;
			$reserve['status'] = str_replace('_', ' ', $reserve['status']);
			$reserve['details'] = json_decode($reserve['details'], true);
			for ($i=0; $i <= sizeof($cart) -1; $i++) {
				$item = $cart[$i];
				$start = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in']);
				$end = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out']);
				$nightsDays = $end->diffInDays($start);
				if($item['rooms'][0]['label'] === 'MONTH'){
					$nightsDays = $end->diffInMonths($start);
				}
				$cart[$i]['price_per_qty'] = number_format(($item['rooms'][0]['tax_price'] * $item['checkoutQty']), 2, '.', '');
				$cart[$i]['price_with_number_of_days'] = number_format(($cart[$i]['price_per_qty'] * $nightsDays), 2, '.', '');
				$reserve['total'] = number_format((float)((double)$reserve['total'] + (double)$cart[$i]['price_with_number_of_days']), 2, '.', '');
				$reserve['subTotal'] = $reserve['total'];
				if(sizeof($reserve['details']['selectedAddOn']) > 0){
					for ($a=0; $a <= sizeof($reserve['details']['selectedAddOn'])-1 ; $a++) {
						$each = $reserve['details']['selectedAddOn'][$a];
						$reserve['total'] = number_format(($reserve['total'] + $each['price']), 2, '.', '');
						$reserve['subTotal'] = number_format((float)$reserve['total'], 2, '.', '');
					}
				}
			}
			if($reserve['coupon_id'] !== null){
				$coupon = app('App\Http\Controllers\CouponController')->retrieveById($reserve['coupon_id']);
				if($coupon['type'] === 'fixed'){
					$reserve['total'] = number_format((float)((double)$reserve['total'] - (double)$coupon['amount']), 2, '.', '');
				}else if($coupon['type'] === 'percentage'){
					$reserve['total'] = number_format((float)((double)$reserve['total'] - ((double)$coupon['amount'] / 100)), 2, '.', '');
				}
			}
			$reserve['account_info'] = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($reserve['account_id'], ['first_name as name', 'cellular_number as contactNumber']);
			$reserve['account_info']['email'] = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($reserve['account_id'], ['email'])['email'];
			$reserve['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $cart[0]['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$reserve['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $cart[0]['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$reserve['coupon'] = $reserve['coupon_id'] !== null ? app('App\Http\Controllers\CouponController')->retrieveById($reserve['coupon_id']) : null;
			$array = array(
				'reservation' => $reserve,
				'cart' => $cart,
				'customer' => $this->retrieveAccountDetails($reserve['account_id']),
			);
			Reservation::where('reservation_code', '=', $data['id'])->update(array(
				'total' => $reserve['total']
			));
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
		$createdAccountId = null;
		$finalResult = [];
		// if($this->validateBeforeCreate($data) == false){
		// 	$this->response['data'] = null;
		// 	$this->response['error'] = 'Apologies, the maximum amount of reservations that we can cater today is already reached';
		// 	return $this->response();
		// }
		$existEmail = app('Increment\Account\Http\AccountController')->retrieveByEmail($data['account_info']->email);
		// dd($existEmail);
		if($existEmail !== null){
			$data['account_id'] = $existEmail['id'];
			$createdAccountId = $data['account_id'];
			$finalResult['message'] = 'Your email is already existed. Please login';
		}else{
			$tempAccount = array(
				'password' => $this->generateTempPassword(),
				'username' => $data['account_info']->email,
				'email' => $data['account_info']->email,
				'account_type' => 'USER',
				'status' => 'NOT_VERIFIED',
				'referral_code' => null
			);
			$acc = app('Increment\Account\Http\AccountController')->createAccount($tempAccount);
			$createdAccountId = $acc;
			$createdAccount = app('Increment\Account\Http\AccountController')->retrieveByEmail($data['account_info']->email);
			if($createdAccount !== null){
				$data['account_id'] = $createdAccount['id'];
				$finalResult['username'] = $tempAccount['username'];
				$finalResult['password'] = $tempAccount['password'];
			}
		}
		$this->insertIntoAccountInformation($data);
		$this->model = new Reservation();
		$temp = Reservation::get();
		$data['code'] = $this->generateCode(sizeof($temp));
		$data['reservation_code'] = $this->generateReservationCode();
		$this->insertDB($data);
		if($this->response['data']){
			$finalResult['reservation_id'] = $this->response['data'];
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
		$this->response['error'] = null;
		$finalResult['account_id'] = $createdAccountId;
		$this->response['data'] = $finalResult;
		return $this->response();
	}

	public function insertIntoAccountInformation($data){
		$existAccount = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($data['account_id'], ['first_name']);
		$customerInfo = array(
			'account_id' => $data['account_id'],
			'first_name' => $data['account_info']->name,
			'cellular_number' => $data['account_info']->contactNumber
		);
		if($existAccount != null){
			app('Increment\Account\Http\AccountInformationController')->updateByAccountId($data['account_id'], $customerInfo);
		}else{
			app('Increment\Account\Http\AccountInformationController')->createByParams($customerInfo);
		}
		$hasPendingReservation = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->where('reservations.account_id', '=', $data['account_id'])->where('reservations.status', '=', 'in_progress')->first();
		if($hasPendingReservation !== null){
			$availability = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', '=', $hasPendingReservation['category_id']);
			if($availability !== null){
				if($data['check_in'] != $availability['start_date'] && $data['check_out'] != $availability['end_date']){
					$this->response['data'] = null;
					$this->response['error'] = 'You cannot add multiple reservation with different check-in and check-out';
					return $this->response();
				}
			}
		}
	}

	public function generateTempPassword(){
		$char = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$code = substr(str_shuffle($char), 0, 8);
		return $code;
	}

	public function validateBeforeCreate($data){
		$isValid = true;
		$startOfDay = Carbon::now()->startOfDay();
		$endOfDay = Carbon::now()->endOfDay();
		$totalReservations = Reservation::where(function($query){
			$query->where('status', '=', 'for_approval');
		})->whereBetween('created_at', [$startOfDay, $endOfDay])->count();
		$parameter = array(
			array('payload', '=', 'reservations')
		);
		$reservationCanCater = app('Increment\Common\Payload\Http\PayloadController')->retrieveByParameter($parameter);
		if((int)$totalReservations >= (int)$reservationCanCater['payload_value']){
			$isValid = false;
		}
		return $isValid; 
	}

	public function update(Request $request){
		$data = $request->all();
		$this->model = new Reservation();
		$reservation = null;
		if(!isset($data['reservation_code'])){
			$confirmed = Reservation::where('id', '=', $data['id'])->first();
			if($confirmed['status'] === 'confirm'){
				$this->response['data'] = null;
				$this->response['error'] = 'Your reservation has been confirmed by the admin';
				return $this->response();;
			}
		}else{
			$reservation = Reservation::where('reservation_code', '=', $data['reservation_code'])->first();
		}
		$data['account_info'] = json_decode($data['account_info']);
		$accountInfo = array(
			'first_name' => $data['account_info']->name,
			'cellular_number' => $data['account_info']->contactNumber
		);
		app('Increment\Account\Http\AccountInformationController')->updateByAccountId($data['account_id'], $accountInfo);
		$cart = json_decode($data['carts']);
		for ($i=0; $i <= sizeof($cart)-1 ; $i++) { 
			$item = $cart[$i];
			$condition = array();
			if(!isset($data['reservation_code'])){
				$condition[] = array('account_id', '=', $data['account_id']);
				$condition[] = array('category_id', '=', $item->category);
				$condition[] = array('price_id', '=', $item->price_id);
				$condition[] = array('deleted_at', '=', null);
				$condition[] = array(function($query){
					$query->where('status', '=', 'in_progress')
					->orWhere('status', '=', 'pending')
					->orWhere('status', '=', 'in_progress');
				});
			}else{
				$condition[] = array('reservation_id', '=', $reservation['id']);
			}
			$updates = array(
				'status' => 'in_progress',
				'qty' => $item->checkoutQty,
				'reservation_id' => $data['id'],
				'check_in' => $data['check_in'],
				'check_out' => $data['check_out'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		}

		if(isset($data['reservation_code'])){
			$update = Reservation::where('reservation_code', '=', $data['reservation_code'])->update(array(
				'details' => $data['details'],
			));
		}else{
			$update = Reservation::where('id', '=', $data['id'])->update(array(
				'details' => $data['details'],
			));
		}
		$this->response['data'] = $update;
		return $this->response();
	}

	public function updateCoupon(Request $request){
		$data = $request->all();
		$condition = array(
			array('account_id', '=', $data['account_id'])
		);
		if(isset($data['reservation_code'])){
			$condition[] = array('reservation_code', '=', $data['reservation_code']);
		}else{
			$condition[] = array('id', '=', $data['id']);
		}
		$reserve = Reservation::where($condition)->first();
		
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where($condition)->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status'],
				'total' => $data['amount']
			));
			$condition = array(
				array('reservation_id', '=', $reserve['id']),
				array('account_id', '=', $data['account_id'])
			);
			$updates = array(
				'status' => $data['status'],
				'updated_at' => Carbon::now()
			);
			$updateCart = app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			if($res !== null){
				$this->sendReceiptById($reserve['id']);
				// $this->sendReceipt($reserve['id']); send email
				$this->response['data'] = $reserve;
			}
			app('App\Http\Controllers\EmailController')->newReservation($data['account_id']);
			
		}
		return $this->response();
	}

	public function updateByParams($condition, $updates){
		return Reservation::where($condition)->update($updates);
	}

	public function updateReservationCart($data){
		$reserve = Reservation::where('reservation_code', '=', $data['code'])->first();
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where('id', '=', $data['id'])->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status']
			));
			$condition = array(
				array('reservation_id', '=', $reserve['id'])
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

	public function generateReservationCode()
	{
		$code = 'res_'.substr(str_shuffle($this->codeSource), 0, 60);
		$codeExist = Reservation::where('reservation_code', '=', $code)->get();
		if(sizeof($codeExist) > 0){
			$this->generateReservationCode();
		}else{
			return $code;
		}
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

	public function retrieveDetails(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$whereArray = array(
			array($con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array('details', 'not like', '%'.'"payment_method":"credit"'.'%')
		);
		if(isset($data['reservation_id'])){
			array_push($whereArray, array('reservation_code', '=', $data['reservation_id']),
			array(function($query){
				$query
					->orWhere('status', '=', 'failed')
					->orWhere('status', '=', 'for_approval')
					->orWhere('status', '=', 'confirmed')
					->orWhere('status', '=', 'cancelled')
					->orWhere('status', '=', 'refunded');
				}
			));
		}else{
			array_push($whereArray, array(function($query){
				$query->where('status', '=', 'in_progress')
					->orWhere('status', '=', 'failed')
					->orWhere('status', '=', 'pending');
				}
			));
		}
		// dd($whereArray);
		$result = Reservation::where($whereArray)->get();
		// dd($result);
		// $rooms = [];
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result) -1; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['payload_value'] =  json_decode($item['payload_value']);;
			}
		}
		if(isset($data['reservation_id'])){
			$reservation = Reservation::where('reservation_code', '=', $data['reservation_id'])->first();
			$data['reservation_id'] = $reservation['id'];
			$carts = app('Increment\Hotel\Room\Http\CartController')->retrieveOwn($data);
		}else{
			$carts = app('Increment\Hotel\Room\Http\CartController')->retrieveOwn($data);
		}
		$accountInfo = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($con[0]['value'], ['first_name as name', 'cellular_number as contactNumber']);
		$accountInfo['email'] = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($con[0]['value'], ['email'])['email'];
		$availability = null;
		if(sizeof($carts) > 0){
			$availability = app('Increment\Hotel\Room\Http\AvailabilityController')->retrieveByPayloadPayloadValue('room_type', $carts[0]['category_id']);
		}
		if(sizeof($result) > 0){
			$available = array(
				'check_in' =>  $item['check_in'],
				'check_out' =>  $item['check_out'],
			);
		}
		$available = array(
			'check_in' =>  $availability !== null ? $availability['start_date'] : null,
			'check_out' =>  $availability !== null ? $availability['end_date'] : null
		);
		$this->response['data']['reservations'] = $result;
		$this->response['data']['availability'] = $available;
		$this->response['data']['carts'] = $carts;
		$this->response['data']['account_info'] = $accountInfo;
		return $this->response();
	}

	public function retrieveByCode(Request $request){
		$data = $request->all();
		$result = Reservation::where('reservation_code', '=', $data['reservation_code'])->first();
		if($result !== null){
			$cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($result['id']);
			if($cart !== null){
				$result['check_in'] = $cart['check_in'];
				$result['check_out'] = $cart['check_out'];
			}
			$result['details'] = json_decode($result['details']);
		}
		$this->response['data'] = $result;
		return $this->response();
	}

	public function countByIds($accountId, $couponId){
		$whereArray = array(
			array(function($query){
				$query->where('status', '=', 'for_approval')
				->orWhere('status', '=', 'completed')
				->orWhere('status', '=', 'confirmed');
			})
		);
		if($accountId === null && $couponId !== null){
			return Reservation::where('coupon_id', '=', $couponId)->where($whereArray)->count();
		}else if($accountId !== null && $couponId === null){
			return Reservation::where('account_id', '=', $accountId)->where($whereArray)->count();
		}else if($accountId !== null && $couponId !== null){
			return Reservation::where('account_id', '=', $accountId)->where('coupon_id', '=', $couponId)->where($whereArray)->count();
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
		$reservation = Reservation::where('reservation_code', '=', $data['roomCode'])->first();
		$res = null;
		if($reservation !== null){
			if($data['status'] === 'completed'){
				app('App\Http\Controllers\EmailController')->sendThankYou($params);
			}else{
				$reservations = $this->getReservationDetails($reservation['id']);
				if($reservations !== null){
					$emailParams = [];
					$start = Carbon::createFromFormat('Y-m-d H:i:s', $reservations['check_in']);
					$end = Carbon::createFromFormat('Y-m-d H:i:s', $reservations['check_out']);
					$nightsDays = $end->diffInDays($start);
					$emailParams['name'] = $this->retrieveName($reservations['account_id']);
					$emailParams['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $reservations['check_in'])->copy()->tz($this->response['timezone'])->format('F d, Y');
					$emailParams['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $reservations['check_out'])->copy()->tz($this->response['timezone'])->format('F d, Y');
					$emailParams['nights'] = $nightsDays;
					$emailParams['adults'] = $reservations['details']->heads;
					$emailParams['children'] = $reservations['details']->child;
					$emailParams['room_types'] = $reservations['room_types'];
					$emailParams['total'] = $reservations['total'];
					$emailParams['add_ons'] = $reservations['add_ons'];
					$emailParams['account_id'] = $reservations['account_id'];
					$emailParams['code'] = $reservation['code'];
					$emailParams['status'] = $data['status'];
					$emailParams['booking_status'] = $reservations['booking_status'];
				}
				app('App\Http\Controllers\EmailController')->sendUpdate($emailParams);
			}
			$res = Reservation::where('reservation_code', '=', $data['roomCode'])->update(array(
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
		}
		$cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($reservation['id']);
		if($cart !== null){
			$params = array(
				'account_id' => $reservation['account_id'],
				'date_of_stay' => Carbon::createFromFormat('Y-m-d H:i:s', $cart['check_in'])->copy()->tz($this->response['timezone'])->format('m/d/Y').' - '.Carbon::createFromFormat('Y-m-d H:i:s', $cart['check_out'])->copy()->tz($this->response['timezone'])->format('m/d/Y'),
				'code' => $reservation['code'],
				'status' => $data['status']
			);
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

	public function retrieveSaleByDate($start, $end){
		$status = array(
            array(function($query){
                $query->where('status', '=', 'confirmed')
                ->orWhere('status', '=', 'for_approval')
                ->orWhere('status', '=', 'completed');
            })
        );
		return Reservation::where($status)->where('deleted_at', '=', null)->sum('total');
	}

	public function retrieveMyBookings(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$whereArray = array(
			array('reservations.'.$con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array('reservations.'.$con[1]['column'], $con[1]['clause'], $con[1]['value']),
			array('T1.'.$con[2]['column'], $con[2]['clause'], $con[2]['value']),
			array('T1.'.$con[3]['column'], $con[3]['clause'], $con[3]['value']),
			array(function($query){
				$query->where('reservations.status', '=', 'for_approval')
					->orWhere('reservations.status', '=', 'confirmed')
					->orWhere('reservations.status', '=', 'completed')
					->orWhere('reservations.status', '=', 'cancelled')
					->orWhere('reservations.status', '=', 'refunded');
			})
		);
		$result = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->leftJoin('pricings as T2', 'T2.id', '=', 'T1.price_id')
			->where($whereArray)
			->where('reservations.account_id', '=', $data['account_id'])
			->groupBy('T1.reservation_id')
			->limit($data['limit'])
			->offset($data['offset'])
			->get(['reservations.*', 'T2.regular', 'T2.refundable', 'T2.currency', 'T2.label', 'T1.check_in', 'T1.check_out']);
		$size = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->leftJoin('pricings as T2', 'T2.id', '=', 'T1.price_id')
			->where($whereArray)
			->where('reservations.account_id', '=', $data['account_id'])
			->groupBy('T1.reservation_id')
			->get(['reservations.*', 'T2.regular', 'T2.refundable', 'T2.currency', 'T2.label']);
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F d, Y');
        $result[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F d, Y');
				$result[$i]['rooms'] = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRoomDetails($item['id']);
			}
		}
		$this->response['size'] = sizeof($size);
		$this->response['data'] = $result;
		return $this->response();
	}

	public function checkout(Request $request){
		$data = $request->all();
		$reservation = Reservation::where('account_id', '=', $data['account_id'])->where('reservation_code', '=', $data['reservation_code'])->first();
		if($reservation !== null){
			$accountEmail = app('Increment\Account\Http\AccountController')->getByParamsWithColumns($data['account_id'], ['email']);
			$accountInformation = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($data['account_id'], ['cellular_number', 'first_name']);
			$details = json_decode($reservation['details']);
			$params = array(
				"account_id" => $data['account_id'],
				"amount" => $data['amount'],
				"name" => $accountInformation->first_name,
				"email" => $accountEmail['email'],
				"referenceNumber" => $reservation['code'],
				"reservation_code" => $reservation['reservation_code'],
				"contact_number" => $accountInformation->cellular_number,
				"payload" => "reservation",
				"payload_value" => $reservation['id']
			);
			$res = app('Increment\Hotel\Payment\Http\PaymentController')->checkout($params);
			$this->response['data'] = $res;
			if($res['data'] !== null){
				Reservation::where('reservation_code', '=', $data['reservation_code'])->update(array(
					'total' => $data['amount'],
					'details' => json_encode($details),
					'status' => $reservation['status'] === 'for_approval' ? 'for_approval' : 'in_progress'
				));
				$this->response['data'] = $res['data'];
			}else{
				$this->response['data'] = $res['error'];
			}
			// $cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($reservation['id']);
			// $priceStatusParams = array(
			// 	'price_id' => $cart['price_id'],
			// 	'category_id' => $cart['category_id']
			// );
			// $existingPriceStatus = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->checkIfPriceExist($priceStatusParams);
			// if(sizeof($existingPriceStatus) > 0){
			// 	$roomPriceUpdate = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateQtyByPriceId($cart['price_id'], $cart['category_id'], ((int)$existingPriceStatus[0]['qty'] - cart['qty']));
			// }
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
		$dateList = CarbonPeriod::create($carbon->toDateTimeString(), $currDate->subDays(1)->toDateTimeString());
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
		$reservation = Reservation::where('reservation_code', '=', $data['code'])->first();
		$details = json_decode($reservation['details']);
		$details->payment_method = 'credit';
		Reservation::where('reservation_code', '=', $data['code'])->update(array(
			'status' => 'for_approval',
			'details' => json_encode($details)
		));
		if($reservation !== null){
			$condition = array(
				array('reservation_id', '=', $reservation['id']),
				array('account_id', '=', $reservation['account_id'])
			);
			$updates = array(
				'status' => 'for_approval',
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		}
		$this->sendReceiptById($reservation['id']);
		app('App\Http\Controllers\EmailController')->newReservation($reservation['account_id']);
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
		$sortBy = null;
		$condition = array(
			array(function($query){
				$query->where('reservations.status', '=', 'for_approval')
					->orWhere('reservations.status', '=', 'confirmed')
					->orWhere('reservations.status', '=', 'completed')
					->orWhere('reservations.status', '=', 'cancelled')
					->orWhere('reservations.status', '=', 'refunded');
			}),
			array('carts.deleted_at', '=', null)
		);

		if($con[0]['column'] == 'check_in' || $con[0]['column'] == 'check_out'){
			$sortBy = 'carts.'.array_keys($data['sort'])[0];
			$condition[] = array('carts.' . $con[0]['column'], $con[0]['clause'], $con[0]['value']);
		}else{
			$sortBy = 'reservations.'.array_keys($data['sort'])[0];
			$condition[] = array('reservations.' . $con[0]['column'], $con[0]['clause'], $con[0]['value']);
		}

		$res = Reservation::leftJoin('carts', 'carts.reservation_id', '=', 'reservations.id')->where($condition)
				->orderBy($sortBy, array_values($data['sort'])[0])
				->limit($data['limit'])
				->offset($data['offset'])
				->groupBy('carts.reservation_id')
				->get();

		$size =  Reservation::leftJoin('carts', 'carts.reservation_id', '=', 'reservations.id')->where($condition)
			->groupBy('carts.reservation_id')
			->orderBy($sortBy, array_values($data['sort'])[0])
			->get();

		for ($i=0; $i <= sizeof($res)-1; $i++) { 
			$item = $res[$i];
			$res[$i]['name'] = app('Increment\Account\Http\AccountInformationController')->getByParamsWithColumns($item['account_id'], ['first_name'])['first_name'];
			$res[$i]['details'] = json_decode($item['details']);
			$res[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['room'] = app('Increment\Hotel\Room\Http\RoomController')->retrieveByIDParams($item['room_id']);
			$res[$i]['total'] = number_format($item['total'], 2, '.', '');
		}

		$this->response['size'] = sizeOf($size);
		$this->response['data'] = $res;
		return $this->response();
	}

	public function delete(Request $request){
		$data = $request->all();
		$res = Reservation::where('id', '=', $data['id'])->update(array(
			'deleted_at' =>  Carbon::now()
		));
		$this->response['data'] = $res;
		return $this->response();
	}

	public function getAssignedQtyByParams($column, $value){
		return Booking::where($column, '=', $value)->where('deleted_at', '=', null)->count();
	}

	public function updateByAdmin(Request $request){
		$data = $request->all();
		$reservation = Reservation::where('reservation_code', '=', $data['reservation_code'])->first();
		$errors = [];
		$couponError = null;
		$couponData = null;
		$category = null;
		if($reservation !== null){
			for ($i=0; $i <= sizeof($data['categories'])-1 ; $i++) {
				$item = $data['categories'][$i];
				$dateAvailable = app('Increment\Hotel\Room\Http\AvailabilityController')->checkIfAvailable('room_type', $item['category_id'], $data['check_in'], $data['check_out']);
				$category = app('Increment\Common\Payload\Http\PayloadController')->retrieveByParams($item['category_id']);
				if($dateAvailable === null){
					array_push($errors, $category['payload_value'].' category is not available during that date');
				}
				$availableRoom = app('Increment\Hotel\Room\Http\RoomController')->availableRoomByCapacity($item['category_id'], $data['heads']);
				if(sizeof($availableRoom) <= 0){
					array_push($errors, $category['payload_value'].' category has no available rooms with that number of people');
				}
				if($data['coupon'] !== null){
					$validCoupon = app('App\Http\Controllers\CouponController')->validCoupon($data['coupon'], $item['category_id'], $data['account_id']);
					if($validCoupon['data'] !== null){
						$couponData = $validCoupon['data'];
						$couponError = null;
					}else{
						$couponData = null;
						$couponError = $validCoupon['error'];
					}
				}
			}
			if($couponError !== null){
				array_push($errors, $couponError);	
			}else{
				Reservation::where('code', '=', $data['reservation_code'])->update(array(
					'coupon_id' => $couponData !== null ? $couponData['id'] : null,
				));
			}
			if(sizeof($errors) > 0){
				$errors = array_unique($errors);
				$this->response['error'] = $errors;
				return $this->response();
			}

			$updateCart = app('Increment\Hotel\Room\Http\CartController')->updateByParams(
				array(
					array('reservation_id', '=', $reservation['id']),
					array('status', '=', 'for_approval')
				),
				array(
					'check_in' => $data['check_in'],
					'check_out' => $data['check_out']
				)
			);
			$details = json_decode($reservation['details']);
			$details->additionals = $data['additional'];
			$details->adults = isset($data['adults']) ? $data['adults'] : $details->adults;
			$details->child = isset($data['children']) ? $data['children'] : $details->child;
			$updateReservation = Reservation::where('reservation_code', '=', $data['reservation_code'])->update(array(
				'details' => json_encode($details),
				'updated_at' => Carbon::now()
			));
			$this->response['data'] = $updateReservation;
		}
		return $this->response();
	}

	public function sendReceipt($reservation_id){
		$reserve = Reservation::where('id', '=', $reservation_id)->first();
		if($reserve !== null){
			$account = $this->retrieveNameOnly($reserve['account_id']);
			$detail = json_decode($reserve['details']);
			$cart = app('Increment\Hotel\Room\Http\CartController')->countReservationId($reservation_id);

			$params = array(
				"code" => $reserve['code'],
				'reservee' => $account,
				'date' => sizeof($cart) > 0 !== null ? $cart[0]['check_in']. ' - '.$cart[0]['check_out'] : null,
				'number_of_heads' => $detail->heads,
				'number_of_rooms' => $cart[0]['totalRooms'],
				'total' => $reserve['total']
			);

			$email = app('App\Http\Controllers')->receipt($reserve['account_id'], $params);
			$this->response['data'] = $email;
		}
		return $this->response();
	}

	public function sendReceiptById($id){
		try{
			$result = Reservation::where('id', '=', $id)->first();
			if($result !== null){
				//Recipt email
				$cart = app('Increment\Hotel\Room\Http\CartController')->getByReservationId($result['id']);
				$reserveDetails = json_decode($result['details']);
				$receiptParams = array(
					'reservee' => $this->retrieveName($result['account_id']),
					'code' => $result['code'],
					'date' => $cart !== null ? Carbon::parse($cart['check_in'])->format('Y-m-d').' - '.Carbon::parse($cart['check_out'])->format('Y-m-d') : 'N/A',
					'status' => $result['status'],
					'number_of_heads' => $reserveDetails->heads,
					'merchant' => env('APP_NAME'),
					'number_of_rooms' => $reserveDetails->totalRoom,
					'payment_method' => $reserveDetails->payment_method,
					'total' => $result['total']
				);
				return app('App\Http\Controllers\EmailController')->receipt($result['account_id'], $receiptParams);
			}
		}catch(\Throwable $th){
			return $th;
		}
	}

	public function getReservationDetails($id){
		$roomTypes = '';
		$addOns = '';
		$temp = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->leftJoin('payloads as T2', 'T2.id', '=', 'T1.category_id')
			->where('reservations.id', '=', $id)
			->where('T1.status', '=', 'for_approval')->get(['reservations.*', 'T1.*', 'T2.payload_value']);
		$result = [];
		if(sizeof($temp) > 0){
			for ($i=0; $i <= sizeof($temp)-1 ; $i++) { 
				$item = $temp[$i];
				$roomTypes .= $item['qty'].' ' . $item['payload_value'] . ($i < (sizeOf($temp)-1) ? ', ' : '');
				$result['check_in'] = $item['check_in'];
				$result['check_out'] = $item['check_out'];
				$result['details'] = json_decode($item['details']);
				$result['account_id'] = $item['account_id'];
				$result['total'] = $item['total'];
				if($result['details']->payment_method == 'checkIn'){
					$result['booking_status'] = 'Payment upon check-in';
				}else if($result['details']->payment_method == 'bank'){
					$result['booking_status'] = 'Bank Payment';
				}else{
					$result['booking_status'] = 'Paid';
				}
				if(sizeOf($result['details']->selectedAddOn)){
					for ($a=0; $a <= sizeOf($result['details']->selectedAddOn)-1 ; $a++) { 
						$each = $result['details']->selectedAddOn[$a];
						$addOns .= $each['title'] . ($a < (sizeof($result['details']->selectedAddOn) - 1) ? ', ' : '');
					}
				}
			}
			$result['room_types'] = $roomTypes;
			$result['add_ons'] = $addOns;
		}
		return $result;
	}
}
