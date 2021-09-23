<?php

namespace Increment\Hotel\Reservation\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use App\TopChoice;
use Increment\Hotel\Reservation\Models\Reservation;
use Carbon\Carbon;
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
		$this->notRequired = array(
			'code'
		);
	}

	public function retrieveWeb(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		if (isset($data['filter'])) {
			$result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
				->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])
				->where($con[2]['column'], $con[2]['clause'], $con[2]['value'])
				->offset($data['offset'])->limit($data['limit'])->orderBy(array_keys($data['sort'])[0], array_values($data['sort'])[0])->get();
		} else {
			$result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
				->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])
				->where($con[2]['column'], $con[2]['clause'], $con[2]['value'])
				->select('id', 'datetime', 'account_id', 'payload_value', 'code', 'status')
				->offset($data['offset'])->limit($data['limit'])
				->orderBy(array_keys($data['sort'])[0], $data['sort'][array_keys($data['sort'])[0]])
				->get();
		}
		if (sizeof($result) > 0) {
			$i = 0;
			foreach ($result as $key) {
				$result[$i]['reservee'] = $this->retrieveNameOnly($result[$i]['account_id']);
				// $result[$i]['synqt'] = app($this->synqtClass)->retrieveByParams('id', $result[$i]['payload_value']);
				// $result[$i]['merchant'] = app($this->merchantClass)->getByParams('id', $result[$i]['merchant_id']);
				// $result[$i]['distance'] = app($this->locationClass)->getLocationDistance('id', $result[$i]['synqt'][0]['location_id'], $result[$i]['merchant']['account_id']);
				// $result[$i]['total_super_likes'] = app($this->topChoiceClass)->countByParams('synqt_id', $result[$i]['payload_value';
				// $result[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('merchant_id', $result[$i]['merchant_id']);
				$result[$i]['date_time_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['datetime'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i:s A');
				$result[$i]['members'] = app($this->messengerGroupClass)->getMembersByParams('payload', $result[$i]['payload_value'], ['id', 'title']);
				$i++;
			}
			$this->response['data'] = $result;
		}
		return $this->response();
	}

	public function retrieve(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		if (isset($data['filter'])) {
			$result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
				->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])
				->where($con[2]['column'], $con[2]['clause'], $con[2]['value'])
				->offset($data['offset'])->limit($data['limit'])->orderBy(array_keys($data['sort'])[0], array_values($data['sort'])[0])->get();
		} else {
			$result = DB::table('messenger_members as T1')
				->leftJoin('messenger_groups as T2', 'T1.messenger_group_id', '=', 'T2.id')
				->where('T1.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
				->offset($data['offset'])->limit($data['limit'])
				->orderBy('T1.' . array_keys($data['sort'])[0], $data['sort'][array_keys($data['sort'])[0]])
				->get();

			$result = json_decode($result, true);
			$result = array_unique($result, SORT_REGULAR);
			// $result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
			// 	->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])
			// 	->where($con[2]['column'], $con[2]['clause'], $con[2]['value'])
			// 	->offset($data['offset'])->limit($data['limit'])
			// 	->orderBy(array_keys($data['sort'])[0], $data['sort'][array_keys($data['sort'])[0]])
			// 	->get();
		}
		$res = null;
		if (sizeof($result) > 0) {
			$j = 0;
			foreach ($result as $value) {
				$tempReserv = Reservation::where('payload_value', '=', $value['payload'])
					->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])
					->where($con[2]['column'], $con[2]['clause'], $con[2]['value'])
					->select('id', 'account_id', 'payload_value', 'merchant_id', 'datetime', 'status')
					->get();
				if (sizeof($tempReserv) > 0) {
					array_push($this->temp, $tempReserv[0]);
				}
				$j++;
			}
			$res = $this->temp;
			if (sizeof($res) > 0) {
				$i = 0;
				foreach ($res as $key) {
					$res[$i]['reservee'] = $this->retrieveNameOnly($res[$i]['account_id']);
					$res[$i]['synqt'] = app($this->synqtClass)->retrieveSynqtByParams('id', $res[$i]['payload_value']);
					$res[$i]['merchant'] = app($this->merchantClass)->getMerchantByParams('id', $res[$i]['merchant_id']);
					$res[$i]['distance'] = app($this->locationClass)->getLocationDistanceByMerchant($res[$i]['synqt'][0]['location_id'], json_decode($res[$i]['merchant']['address']));
					$res[$i]['total_super_likes'] = app($this->topChoiceClass)->countByParams($res[$i]['payload_value'], $res[$i]['merchant_id']);
					$res[$i]['rating'] = app($this->ratingClass)->getRatingByPayload('merchant_id', $res[$i]['merchant_id']);
					$res[$i]['date_time_at_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $res[$i]['datetime'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i A');
					$res[$i]['members'] = app($this->messengerGroupClass)->getMembersByParams('payload', $res[$i]['payload_value'], ['id', 'title']);
					$i++;
				}
				$this->response['data'] = $res;
			}
		}
		return $this->response();
	}

	public function create(Request $request)
	{
		$data = $request->all();
		$this->model = new Reservation();
		$this->insertDB($data);
		// if ($this->response['data'] !== null) {
		// 	TopChoice::where('synqt_id', '=', $data['payload_value'])->update(array(
		// 		'deleted_at' => Carbon::now()
		// 	));
		// }

		return $this->response();
	}

	public function retrieveByParams($whereArray, $returns)
	{
		$result = Reservation::where($whereArray)->get($returns);
		return sizeof($result) > 0 ? $result[0] : null;
	}

	public function generateCode()
	{
		$code = 'res_' . substr(str_shuffle($this->codeSource), 0, 60);
		$codeExist = Reservation::where('code', '=', $code)->get();
		if (sizeof($codeExist) > 0) {
			$this->generateCode();
		} else {
			return $code;
		}
	}

	public function update(Request $request)
	{
		$data = $request->all();
		$emailData = array();
		$reservation = Reservation::where('id', '=', $data['id'])->get();
		$noOfGuests = app($this->messengerGroupClass)->getMembersByParams('payload', $reservation[0]['payload_value'], ['id', 'title']);
		$emailData = array(
			'reservee' => $this->retrieveNameOnly($reservation[0]['account_id']),
			'date' =>  Carbon::createFromFormat('Y-m-d H:i:s', $reservation[0]['datetime'])->copy()->tz($this->response['timezone'])->format('F j, Y H:i A'),
			'number_of_guests' => $noOfGuests !== null ? sizeof($noOfGuests) : 0,
			'merchant' => app($this->merchantClass)->getByParams('id', $reservation[0]['merchant_id']),
			'code' => $this->generateCode(),
			'status' => ucfirst($data['status'])
		);
		// $email = app($this->emailClass)->receipSynqt($emailData, $reservation[0]['account_id']);
		// if ($email !== null) {
		$result = Reservation::where('id', '=', $data['id'])->update(array(
			'status' => $data['status'],
			'code' => $this->generateCode(),
		));
		$this->response['data'] = $result;
		// }
		return $this->response();
	}

	public function retrieveBookings(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		$sortBy = 'reservations.'.array_keys($data['sort'])[0];
		$condition = array(
			array('reservations.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
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
		$res = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->where($condition)
			->orderBy($sortBy, array_values($data['sort'])[0])
			->limit($data['limit'])
			->offset($data['offset'])
			->get(['reservations.*', 'T2.email', 'T3.title', 'T5.regular']);

		$size = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->where($condition)
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
		$res = Reservation::where('account_id', '=', $accountId)->get(['payload_value']);
		$total = 0;
		for ($i=0; $i <= sizeof($res)-1; $i++) { 
			$item = $res[$i];
			$rooms = app($this->roomController)->retrieveTotalPriceById($accountId, 'id', $item['payload_value'], ['price']);
			if(sizeof($rooms) > 0){
				$total += $rooms[0]['price'];
			}
		}
		return $total;
	}

	public function getTotalBookings($date){
		$bookings = Reservation::where('check_in', '<=', $date)->where('deleted_at', '=', null)->count();
		$reservations = Reservation::where('check_in', '>', $date)->where('deleted_at', '=', null)->count();
		return array(
			'previous' => $bookings,
			'upcommings' => $reservations
		);
	}
}
