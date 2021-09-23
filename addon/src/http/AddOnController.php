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
		$results = DB::table('add_ons')
			->where('deleted_at', '=', null)
			->limit($data['limit'])
			->offset($data['offset'])
			->get();
		$this->response['data'] = $results;
		$this->response['size'] = AddOn::where('deleted_at', '=', null)->count();
		return $this->response();
	}

    public function retrieveAll(Request $request) {
		$data = $request->all();
		$results = DB::table('add_ons')
			->where('deleted_at', '=', null)
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
        $this->model = new AddOn();
        $this->insertDB($data);
		return $this->response();
	}
}
