<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Availability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AvailabilityController extends APIController
{
    function __construct(){
        $this->notRequired = array(
           'description', 'start_date', 'end_date', 'limit'
        );
    }
    public function create(Request $request){
		$data = $request->all();
        $this->model = new Availability();
        $tax = app('Increment\Hotel\Room\Http\RoomTypeController')->getTax($data['payload_value']);
        $data['room_price'] = floatval($data['room_price']) + floatval($tax);
        if($data['limit_per_day'] > 0){
            $this->manageDates($data);
        }else{
            $this->manageBlocking($data);
        }
		return $this->response();
	}

    public function manageDates($data){
        $existStartDate = Availability::where('payload_value', '=', $data['payload_value'])
            ->where('start_date', '<=', $data['start_date'])
            ->where('add_on', '=', $data['add_on'])
            ->orderBy('start_date', 'desc')
            ->first();
        $existEndDate = Availability::where('payload_value', '=', $data['payload_value'])
            ->where('end_date', '>=', $data['end_date'])
            ->where('add_on', '=', $data['add_on'])
            ->orderBy('end_date', 'asc')
            ->first();

        if($existStartDate && $existEndDate && $existEndDate['id'] == $existStartDate['id']){
            $sDate = $data['start_date'].' 00:00:00';
            $eDate = $data['end_date'].' 00:00:00';

            if($sDate == $existStartDate['start_date'] && $eDate == $existStartDate['end_date']){ //updating the same range
                $updated = Availability::where('id', '=', $existStartDate['id'])->update(array(
                    'limit_per_day' => $data['limit_per_day'],
                    'add_on' => $data['add_on'],
                    'status' => $data['status'],
                    'description' => $data['description'],
                    'room_price' => $data['room_price'],
                    'updated_at' => Carbon::now()
                ));
                return 1;
            }
        }
        
        if($existStartDate != null){
            $esd = Carbon::parse($existStartDate['start_date']);
            $eed = Carbon::parse($existStartDate['end_date']);
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            if($existStartDate['start_date'] != ($data['end_date'].' 00:00:00')){ // updating existing range with new end date
                $updated = Availability::where('id', '=', $existStartDate['id'])->update(array(
                    'end_date' => $startDate->subDays(1)
                ));
            }else{
                //start date of exising range = end date of given range
                $updated = Availability::where('id', '=', $existStartDate['id'])->update(array( // updating the existing range with new start date 
                    'start_date' => $startDate->addDay()
                ));
            }
        }
        $this->insertData($data);
        if($existEndDate != null){
            $esd = Carbon::parse($existEndDate['start_date']);
            $eed = Carbon::parse($existEndDate['end_date']);
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            if($existStartDate != null && $existStartDate['id'] == $existEndDate['id']){
                if($startDate != $eed && $startDate != $esd){
                    //continuing the other range for the cutted existing data
                    // insert new date
                    $nStartDate = Carbon::parse($data['end_date']);
                    $newModel = new Availability();
                    $newModel->payload = 'room_type';
                    $newModel->payload_value = $existStartDate['payload_value'];
                    $newModel->start_date = $nStartDate->addDay();
                    $newModel->end_date = $existStartDate['end_date'];
                    $newModel->limit_per_day = $existStartDate['limit_per_day'];
                    $newModel->description = $existStartDate['description'];
                    $newModel->room_price = $existStartDate['room_price'];
                    $newModel->add_on = $existStartDate['add_on'];
                    $newModel->status = $existStartDate['status'];
                    $newModel->save();
                }
                
            }else{
                if($esd < $endDate && $endDate == $eed){
                    //existing end date range is within the range of given data
                    $updated = Availability::where('id', '=', $existEndDate['id'])->update(array(
                        'deleted_at' => Carbon::now()
                    ));
                }else{
                    //updating exising date range with new start date
                    $updated = Availability::where('id', '=', $existEndDate['id'])->update(array(
                        'start_date' => $endDate->addDay()
                    ));
                }
                
            }
        }
        if($existStartDate && $existEndDate){
            //deleting ranges not equal to inserted data which are within the given range
            Availability::whereBetween('start_date', [$data['start_date'], $data['end_date']])
            ->whereBetween('end_date', [$data['start_date'], $data['end_date']])
            ->where('id', '!=', $this->response['data'])
            ->update(array('deleted_at' => Carbon::now()));
        }else if($existStartDate == null && $existEndDate == null){
            //deleting all ranges within the given ranges
            //expanding existing range with lesser start date and bigger end date
            Availability::whereBetween('start_date', [$data['start_date'], $data['end_date']])
            ->whereBetween('end_date', [$data['start_date'], $data['end_date']])
            ->where('id', '!=', $this->response['data'])
            ->update(array('deleted_at' => Carbon::now()));
        }
    }

    public function manageBlocking($data){

    }

    public function  insertData($data){
        $this->model = new Availability();
        $this->insertDB($data);
        return $this->response;
    }


    public function retrieve(Request $request){
        $data = $request->all();
        $con = $data['condition'];
        $res = Availability::leftJoin('payloads as T1', 'T1.id', '=', 'availabilities.payload_value')
            ->where($con[0]['column'] == 'payload_value' ? 'T1.'.$con[0]['column'] : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
            ->where('availabilities.payload', '=', 'room_type')
            ->limit($data['limit'])
            ->offset($data['offset'])
            ->orderBy($con[0]['column'] == 'payload_value' ? 'T1.'.array_keys($data['sort'])[0] : array_keys($data['sort'])[0], array_values($data['sort'])[0])
            ->get(['availabilities.id', 'availabilities.limit_per_day', 'start_date', 'end_date', 'T1.payload_value', 'T1.id as room_type', 'limit', 'status']);
        
        $size = Availability::leftJoin('payloads as T1', 'T1.id', '=', 'availabilities.payload_value')
            ->where($con[0]['column'] == 'payload_value' ? 'T1.payload_value' : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
            ->where('availabilities.payload', '=', 'room_type')
            ->get();
        for ($i=0; $i <= sizeof($res)-1 ; $i++) { 
            $item = $res[$i];
            $start_date = Carbon::now()->startOfDay();
            $end_date = Carbon::now()->endOfDay();
            $cartsPerDay = app('Increment\Hotel\Room\Http\CartController')->countDailyCarts($start_date, $end_date, $item['room_type']);
            $res[$i]['remaining_qty'] = (float)$item['limit_per_day'] - (float)$cartsPerDay;
            $res[$i]['start_date'] = $item['start_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['start_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
            $res[$i]['end_date'] = $item['end_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
        }
        $this->response['data'] = $res;
        $this->response['size'] = sizeof($size);
        
        return $this->response();
    }

    public function retrieveTypeByCode(Request $request){
        $data = $request->all();
        $result = Availability::where('payload', '=', 'room_type')->where('payload_value', '=', $data['id'])->get();
        // for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
        //     $item = $result[$i];
        //     $result[$i]['start_date'] = $item['start_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['start_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
        //     $result[$i]['end_date'] = $item['end_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
        // }
        $this->response['data'] = $result;
        return $this->response();
      } 

    public function retrieveById(Request $request){
        $data = $request->all();
        if(isset($data['room_code'])){
            $roomId = app('Increment\Hotel\Room\Http\RoomController')->retrieveIDByCode($data['room_code']);
            $result = Availability::where('payload', '=', 'room_id')->where('payload_value', '=', $roomId[0]['id'])->get();
        }else{
            $con = $data['condition'];
            $result = Availability::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])->where($con[1]['column'], $con[1]['clause'], $con[1]['value'])->get();
        }
        for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
            $item = $result[$i];
            $carts =  app('Increment\Hotel\Room\Http\CartController')->countByCategory($item['payload_value']);
            $result[$i]['remaining_qty'] = (float)$item['limit_per_day'] - (float)$carts;
            $result[$i]['start_date'] = $item['start_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['start_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
            $result[$i]['end_date'] = $item['end_date'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date'])->copy()->tz($this->response['timezone'])->format('F d, Y') : null;
        }
        $this->response['data'] = $result;
        return $this->response();
    }

    public function compareDates(Request $request){
        $data = $request->all();
        $date1 = Availability::where('payload_value', '=', $data['category_id1'])->where('payload', '=', 'room_type')->first();
        $date2 = Availability::where('payload_value', '=', $data['category_id2'])->where('payload', '=', 'room_type')->first();
        if($date1['start_date'] == $date2['start_date']){
            $this->response['data'] = true;
        }else{
            $this->response['data'] = false;
        }
        return $this->response();
    }

    public function update(Request $request){
        $data = $request->all();
        $params = array(
            'payload' => $data['payload'],
            'payload_value' => $data['payload_value'],
            'limit' => $data['limit'],
            'limit_per_day' => $data['limit_per_day'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'updated_at' => Carbon::now()
        );
        if(isset($data['description'])){
            $params['description'] = $data['description'];
        }
        $result = Availability::where('id', '=', $data['id'])->update($params);
        $avail = array(
					'status' => $data['status'] === 'available' ? 'publish' : 'pending'
				);
				$avail['updated_at'] = Carbon::now();
				$con = array(
					'id' => $data['payload_value']
				);
        $res = app('Increment\Hotel\Room\Http\RoomController')->updateByParams($con, $avail);
        $this->response['data'] = $result;
        $this->response['eror'] = null;
        return $this->response();
    }

    public function retrieveStatus($roomId){
        return Availability::where('payload_value', '=', $roomId)->first();
    }

    public function updateByParams($condition, $params){
        return Availability::where($condition)->update($params);
    }

    public function createByParams($data){
        $exist = Availability::where('payload_value', '=', $data['payload_value'])->where('payload', '=', 'room_id')->get();
        if(sizeof($exist) > 0){
            $this->response['error'] = 'Already existed';
        }else{
            $this->model = new Availability();
            $this->insertDB($data);
        }
		return $this->response();
    }

    public function retrieveByPayloadPayloadValue($payload, $payloadValue){
        return Availability::where('payload_value', '=', $payloadValue)->where('payload', '=', $payload)->first();
    }

    public function checkIfAvailable($payload, $payloadValue, $startDate, $endDate){
        $data = [];
        $checkIn = Availability::where('payload_value', '=', $payloadValue)
            ->where('payload', '=', $payload)
            ->where('start_date', '<=', Carbon::parse($startDate)->format('Y-m-d'))
            ->where('limit_per_day', '>', 0)
            ->first();
        if($checkIn == null){
            $data['data'] = null;
            $data['error'] = 'This room type is not available during the set start date';
            return $data;
        }
        // dd($payload, $payloadValue, $startDate, $endDate);
        $checkOut = Availability::where('payload_value', '=', $payloadValue)
            ->where('payload', '=', $payload)
            ->where('end_date', '>=', Carbon::parse($endDate)->format('Y-m-d'))
            ->where('limit_per_day', '>', 0)
            ->first();
        // dd($checkOut);
        if($checkOut == null){
            $data['data'] = null;
            $data['error'] = 'This room type is not available during the set end date';
            return $data;
        }
        $data['data'] = true;
        $data['error'] = null;
        return $data;
    }

    public function retrieveByRoomType(Request $request){
        $data = $request->all();
        $condition = array(
            array('payload_value', '=', $data['payload_value']),
            array('payload', '=', $data['payload']),
            array('add_on', '=', $data['addOn'])
        );
        if($data['id'] != null){
            $condition[] = array('id', '=', $data['id']);
        }else{
            $condition[] = array('start_date', '=', $data['start_date']);
        }
        $result = Availability::where($condition)
        ->select(['start_date', 'end_date', 'id', 'limit', 'description', 'limit_per_day'])->first();
        
        if($result != null){
            $result['description'] = json_decode($result['description']);
        }
        $this->response['data'] = $result;
        return $this->response();
    }

    public function retrieveAll(){
        $result = Availability::get();
        if(sizeof($result) > 0){
            for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
                $item = $result[$i];
                $result[$i]['description'] = json_decode($item['description'], true);
                $roomPrice = $result[$i]['room_price'];
                $breakFast = $result[$i]['description']['break_fast'];
                $result[$i]['price'] = floatval($roomPrice) + floatval($breakFast);
            }
        }
        return $result;
    }

    public function retrieveWithCondition($data){
        $condition = array(
            array('payload_value', '=', $data['room_type']),
            array('add_on', '=', $data['add_on']),
            array('deleted_at', '=', null)
        );
        $result = Availability::where($condition)->get();
        if(sizeof($result) > 0){
            for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
                $item = $result[$i];
                $result[$i]['description'] = json_decode($item['description'], true);
                $roomPrice = $result[$i]['room_price'];
                $breakFast = $result[$i]['description']['break_fast'];
                $result[$i]['price'] = floatval($roomPrice);
            }
        }
        return $result;
    }

    public function retrieveMaxMin(){
		$min = Availability::where('deleted_at', '=', null)->orderBy('room_price', 'asc')->first();
		$max = Availability::where('deleted_at', '=', null)->orderBy('room_price', 'desc')->first();
		return array(
			'min' => $min !== null ? (int)$min['room_price'] : 0,
			'max' => $max !== null ? (int)$max['room_price'] : 0
		);
	}

    public function isAvailable($roomType, $checkIn, $checkOut){
        $carts = app('Increment\Hotel\Room\Http\CartController')->countDailyCarts($checkIn, null, $roomType);
        $availability = Availability::where('payload_value', '=', $roomType)->where('start_date', '<=', $checkIn)->where('limit_per_day', '>', 0)->get();
        if(sizeof($availability) > 0){
            if((int)$carts < $availability[0]['limit_per_day']){
                return true;
            }else{
                return false;
            }
        }
        return false;
    }

    public function getDetails($category, $startDate){
        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $temp = Availability::leftJoin('payloads as T1', 'T1.id', '=', 'availabilities.payload_value')
            ->where('availabilities.start_date', '<=', $startDate)
            ->where('availabilities.end_date', '>=', $startDate)
            ->where('availabilities.payload_value', '=', $category)
            ->select('availabilities.id as availabilityId', 'T1.id as categoryId', 'T1.payload_value as room_type', 'availabilities.*', 'T1.capacity',
            'T1.category as general_description', 'T1.details as general_features', 'T1.tax', 'T1.price_label', 'limit_per_day')
            ->first();
        if($temp !== null){
            $temp['general_features'] = json_decode($temp['general_features'], true);
            $temp['description'] = json_decode($temp['description'], true);
            $temp['general_description'] = $temp['general_description'];
            $temp['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($temp['categoryId'], 'room_type');
        }
        return $temp;
    }

    public function retrieveByIds($categoryId, $startDate){
        $startDate= Carbon::parse($startDate)->format('Y-m-d');
        $result = Availability::where('payload_value', '=', $categoryId)->where('start_date', '<=', $startDate)->where('end_date', '>=', $startDate)->first();
        if($result !== null){
            $result['description'] = json_decode($result['description'], true);
        }
        return $result;
    }

    public function checkAvailability($toBeInsert, $checkIn, $category){
        $carts = app('Increment\Hotel\Room\Http\CartController')->countDailyCarts($checkIn, null, $category);
        $checkIn = Carbon::parse($checkIn)->format('Y-m-d');
        $temp = Availability::where('payload_value', '=', $category)->where('start_date', '<=', $checkIn)->where('limit_per_day', '>', 0)->first();
        if(((int)$toBeInsert + (int)$carts) <= $temp['limit_per_day']){
            return true;
        }
        return false;
    }

    public function getRemainingQty($checkIn, $category){
        $carts = app('Increment\Hotel\Room\Http\CartController')->countDailyCarts($checkIn, null, $category);
        $temp = Availability::where('payload_value', '=', $category)->where('start_date', '<=', $checkIn)->where('end_date', '>=', $checkIn)->where('limit_per_day', '>', 0)->orderBy('room_price', 'asc')->first();
        if((int)$carts > 0){
            return (int)$carts - $temp['limit_per_day'];
        }else{
            return $temp['limit_per_day'];
        }
    }
}
