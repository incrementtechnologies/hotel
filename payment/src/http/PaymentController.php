<?php

namespace Increment\Imarket\Payment\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Imarket\Payment\Models\Payment;
use DB;
class PaymentController extends APIController
{
   	function __construct(){
   		$this->model = new Payment();
   	}

		public function createByParams($data){
			$data['code'] = $this->generateCode();
			$this->insertDB($data);
			return $this->response['data'];
		}

		public function generateCode(){
			$code = 'pay_'.substr(str_shuffle($this->codeSource), 0, 60);
			$codeExist = Payment::where('code', '=', $code)->get();
			if(sizeof($codeExist) > 0){
				$this->generateCode();
			}else{
				return $code;
			}
		}
}
