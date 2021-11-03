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
           'description'
        );
    }
    public function create(Request $request){
		if($this->checkAuthenticatedUser() == false){
		  return $this->response();
		}
		$data = $request->all();
        $exist = Availability::where('payload_value', '=', $data['payload_value'])->get();
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
            ->where($con[0]['column'] == 'type' ? 'T1.'.$con[0]['column'] : $con[0]['column'], $con[0]['clause'], $con[0]['value'])
            ->limit($data['limit'])
            ->offset($data['offset'])
            ->orderBy(array_keys($data['sort'])[0], array_keys($data['sort'])[0])
            ->get(['availabilities.id', 'start_date', 'end_date', 'T1.payload_value', 'limit', 'status']);
        for ($i=0; $i <= sizeof($res)-1 ; $i++) { 
            $item = $res[$i];
            $res[$i]['start_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['start_date'])->copy()->tz($this->response['timezone'])->format('F d, Y');
            $res[$i]['end_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date'])->copy()->tz($this->response['timezone'])->format('F d, Y');
        }
        $this->response['data'] = $res;
        return $this->response();
    }

    public function retrieveById(Request $request){
        $data = $request->all();
        $result = Availability::where('id', '=', $data['id'])->get();
        $this->response['data'] = $result;
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
        $result = Availability::where('id', '=', $data['id'])->update($params);
        $this->response['data'] = $result;
        return $this->response();
    }

    public function retrieveStatus($roomId){
        return Availability::where('payload_value', '=', $roomId)->first();
    }
}
