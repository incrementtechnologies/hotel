<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Availability;
use Carbon\Carbon;

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
        if($data['limit_per_day'] == 0){
           $this->manageCreateUpdate($data);
        }else{
            $exist  = Availability::where('payload_value', '=', $data['payload_value'])->where('start_date', '=', $data['start_date'])->where('limit_per_day', '>', 0)->first();
            if($exist !== null){
                $update = Availability::where('id', '=', $exist['id'])->update($data);
            }else{
                $this->manageCreateUpdate($data);
            }
        }
		return $this->response();
	}

    public function manageCreateUpdate($data){
        $existStartDate = Availability::where('payload_value', '=', $data['payload_value'])
            ->where('start_date', '<=', $data['start_date'])
            ->orderBy('id', 'desc')
            ->first();
        $existEndDate = Availability::where('payload_value', '=', $data['payload_value'])
            ->where('end_date', '>=', $data['end_date'])
            ->orderBy('id', 'desc')
            ->first();
        if($existStartDate !== null && $existEndDate !== null){
            $hasBlockedDates = Availability::where('payload_value', '=', $data['payload_value'])
                ->where('start_date', '>=', $data['start_date'])
                ->where('end_date', '<=', $data['end_date'])
                ->where('status', '=', $data['limit_per_day'] == 0 ? 'available' : 'not_available')
                ->get();
            if(sizeof($hasBlockedDates) > 0){
                for ($i=0; $i <= sizeof($hasBlockedDates)-1 ; $i++) { 
                    $item = $hasBlockedDates[$i];
                    Availability::where('id', '=', $item['id'])->update(array('limit_per_day' => $data['limit_per_day'], 'status' => $data['limit_per_day'] == 0 ? 'not_available' : 'available'));
                }
                $this->response['data'] = 'Updated avaiable date';
            }else{
                $newEndDate = Carbon::parse($data['start_date'])->subDays(1);
                $newStartDate = Carbon::parse($data['end_date'])->addDay();
                $updateFirst = Availability::where('id', '=', $existStartDate['id'])->update(array('end_date' => $newEndDate));
                if($updateFirst){
                    $data['status'] = $data['limit_per_day'] == 0 ? 'not_available' : 'available';
                    $createNewBlock = $this->insertDB($data);
                    if($createNewBlock && (Carbon::parse($data['end_date']) < Carbon::parse($existEndDate['end_date']) || Carbon::parse($data['start_date']) < Carbon::parse($existEndDate['end_date']))){
                        $newModel = new Availability();
                            $newModel->payload = 'room_type';
                            $newModel->payload_value = $existStartDate['payload_value'];
                            $newModel->start_date = $newStartDate;
                            $newModel->end_date = $existEndDate['end_date'];
                            $newModel->limit_per_day = $existEndDate['limit_per_day'];
                            $newModel->description = $existEndDate['description'];
                            $newModel->room_price = $existEndDate['room_price'];
                            $newModel->status = $existEndDate['status'];
                        $createNewEndOfFirst = $newModel->save();
                        $this->response['data'] = 'Date Updated';
                    }else{
                        $this->response['data'] = null;
                        $this->response['error'] = 'Error in creating new';
                    }
                }else{
                    $this->response['data'] = null;
                    $this->response['error'] = 'Error in updating first';
                }
            }
        }else{
            if($existStartDate !== null && $existEndDate == null){
                $newEndDate = Carbon::parse($data['start_date'])->subDays(1);
                $updateFirst = Availability::where('id', '=', $existStartDate['id'])->update(array('end_date' => $newEndDate));
                if($updateFirst){
                    $data['status'] = 'not_available';
                    $createNewBlock = $this->insertDB($data);
                    $this->response['data'] = $createNewBlock;
                    $this->response['error'] =  null;
                }

            }else{
                $this->insertDB($data);
            }
        }
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
        return Availability::where('payload_value', '=', $payloadValue)
            ->where('payload', '=', $payload)
            ->where('start_date', '<=', $startDate)
            ->where('end_date', '>=', $endDate)
            ->first();
    }

    public function retrieveByRoomType(Request $request){
        $data = $request->all();
        $result = Availability::where('payload', '=', $data['payload'])
        ->where('payload_value', '=', $data['payload_value'])
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
            array('payload_value', '=', $data['room_type'])
        );
        $condition[] = array(function($query)use($data){
            if($data['add_on'] == 'Breakfast'){
                $query->where('description', 'not like', '%"break_fast":"0"%')
                ->where('description', 'not like', '%"room_price":"0"%');
            }else if($data['add_on'] == null){
                $query->where('description', 'like', '%"break_fast":"0"%')
                ->where('description', 'not like', '%"room_price":"0"%');
            }else if($data['add_on'] == 'Room Only'){
                $query->where('description', 'like', '%"break_fast":"0"%')
                ->where('description', 'not like', '%"room_price":"0"%');
            }else if($data['add_on'] == 'Breakfast Only'){
                $query->where('description', 'not like', '%"break_fast":"0"%')
                ->where('description', 'like', '%"room_price":"0"%');   
            }
        });
        $result = Availability::where($condition)->get();
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

    public function retrieveMaxMin(){
		$min = Availability::where('deleted_at', '=', null)->orderBy('room_price', 'asc')->first();
		$max = Availability::where('deleted_at', '=', null)->orderBy('room_price', 'desc')->first();
		return array(
			'min' => $min !== null ? (int)$min['room_price'] : 0,
			'max' => $max !== null ? (int)$max['room_price'] : 0
		);
	}

    public function isAvailable($roomType, $checkIn, $checkOut){
       $startDate = Availability::where('payload_value', '=', $roomType)->where('start_date', '<=', $checkIn)->get();
       $endDate =  Availability::where('payload_value', '=', $roomType)->where('end_date', '>=', $checkOut)->get();
       if(sizeof($startDate) > 0 && sizeof($endDate) > 0){
        return true;
       }
       return false;
    }

    public function getDetails($category, $availabilty){
        $temp = Availability::leftJoin('payloads as T1', 'T1.id', '=', 'availabilities.payload_value')
            ->where('T1.id', '=', $category)->where('availabilities.id', '=', $availabilty)
            ->select('availabilities.id as availabilityId', 'T1.id as categoryId', 'T1.payload_value as room_type', 'availabilities.*', 'T1.capacity',
            'T1.category as general_description', 'T1.details as general_features', 'T1.tax')
            ->first();
        if($temp !== null){
            $temp['general_features'] = json_decode($temp['general_features'], true);
            $temp['description'] = json_decode($temp['description'], true);
            $temp['general_description'] = $temp['general_description'];
            $temp['images'] = app('Increment\Hotel\Room\Http\ProductImageController')->retrieveImageByStatus($temp['categoryId'], 'room_type');
        }
        return $temp;
    }

    public function retrieveByIds($categoryId, $availabiltyid){
        $result = Availability::where('payload_value', '=', $categoryId)->where('id', '=', $availabiltyid)->first();
        if($result !== null){
            $result['description'] = json_decode($result['description'], true);
        }
        return $result;
    }
}
