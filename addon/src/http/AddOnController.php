<?php

namespace Increment\Hotel\AddOn\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\AddOn\Models\AddOn;
use DB;

class AddOnController extends APIController
{
    //
    function __construct(){
        $this->model = new AddOn();
        $this->notRequired = array('merchant_id', 'url');
    }

    public function retrieve(Request $request) {
		$data = $request->all();
		$con = $data['condition'];
		$sortBy = 'add_ons.'.array_keys($data['sort'])[0];
		$condition = array(
			array('add_ons.' . $con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array('add_ons.' . $con[1]['column'], $con[1]['clause'], $con[1]['value'])
		);
		$results = DB::table('add_ons')
			->where($condition)
			->where('deleted_at', '=', null)
			->limit($data['limit'])
			->offset($data['offset'])
			->orderBy(array_keys($data['sort'])[0], array_values($data['sort'])[0])
			->get();
		$this->response['data'] = $results;
		$this->response['size'] = AddOn::where($con[1]['column'], $con[1]['clause'], $con[1]['value'])->where('deleted_at', '=', null)->count();
		return $this->response();
	}

    public function retrieveAll(Request $request) {
		$data = $request->all();
		$results = DB::table('add_ons')
			->where('deleted_at', '=', null)
			->where('type', '=', $data['type'])
			->get();
		$this->response['data'] = $results;
		$this->response['size'] = AddOn::where('deleted_at', '=', null)->count();
		return $this->response();
	}

    public function create(Request $request){
		if($this->checkAuthenticatedUser() == false){
		  return $this->response();
		}
		$data = $request->all();
		$exist = AddOn::where('title', '=', $data['title'])->where('deleted_at', '=', null)->first();
		if($exist !== null){
			$this->response['error'] = 'Already existed add-on';
			$this->response['data'] = null;
			return $this->response();
		}else{
			$this->model = new AddOn();
			$this->insertDB($data);
			$this->response['error'] = null;
			return $this->response();
		}
	}
}
