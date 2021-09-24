<?php

namespace Increment\Imarket\Coupon\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Imarket\Coupon\Models\Coupon;
use DB;
class CouponController extends APIController
{
   	function __construct(){
   		$this->model = new Coupon();
   		$this->notRequired = array('scope', 'minimum_amount', 'quota');
   	}

   	public function getCoupon($id){
      $result = Coupon::where('id', '=', $id)->get();
      return (sizeof($result) > 0) ? $result[0] : null;
	}

	public function getCouponOnCode($code){
		$results = Coupon::where('code', '=', $code)->get();
		return (sizeof($results) > 0) ? $results[0] : null;
	}
	
	public function create(Request $request){
		$this->rawRequest = $request;
		if($this->checkAuthenticatedUser() == false){
		  return $this->response();
		}
		$data = $request->all();
		$codeExist = Coupon::where('value', '=', $data['code'])->get();
		if (sizeof($codeExist)<1){
			$this->model = new Coupon;
			$this->insertDB($request->all());
		}
		return $this->response();
	}

	public function useCoupon(Request $request){
		$data = $request->all();
		//check if coupon passed exists and is still valid in the date period used
		$valid = Coupon::where('start', '<=', $data['date'])->where('end', '>=', $data['date'])->where('value', '=', $data['code'])->where('scope', '=', $data['scope'])->get();
		if (sizeof($valid)>0 && $valid[0]['quota'] != NULL){
			Coupon::where('id', '=', $valid[0]['id'])->get();
		}
	}

	public function retrieve(Request $request) {
		$data = $request->all();
		$results = DB::table('coupons')
			->limit($data['limit'])
			->offset($data['offset'])
			->get();
		$this->response['data'] = $results;
		$this->response['size'] = Coupon::where('deleted_at', '=', null)->count();
		return $this->response();
	}
}
