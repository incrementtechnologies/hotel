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
		if($this->checkAuthenticatedUser() == false){
		  return $this->response();
		}
		$data = $request->all();
        $exist = Availability::where('payload_value', '=', $data['payload_value'])->where('payload', '=', 'room_type')->get();
        if(sizeof($exist) > 0){
            $this->response['error'] = 'Already existed';
        }else{
            $this->model = new Availability();
            $this->insertDB($data);
        }
		return $this->response();
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
            ->get(['availabilities.id', 'start_date', 'end_date', 'T1.payload_value', 'limit', 'status']);
        
        $size = Availability::leftJoin('payloads as T1', 'T1.id', '=', 'availabilities.payload_value')
            ->where($con[0]['column'] == 'payload_value' ? 'T1.payload_value' : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
            ->where('availabilities.payload', '=', 'room_type')
            ->get();
        for ($i=0; $i <= sizeof($res)-1 ; $i++) { 
            $item = $res[$i];
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
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'updated_at' => Carbon::now()
        );
        if(isset($data['description'])){
            $params['description'] = $data['description'];
        }
        if($data['payload'] === 'room_type'){
            $used = app('Increment\Hote\Room\Http\CartController')->getByCategory($data['payload_value']);
            if(sizeof($used) > 0){
                $this->response['data'] = null;
                $this->response['error'] = 'This category is currently used';
                return $this->response();
            }
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
}
