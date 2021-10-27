<?php

namespace Increment\Hotel\Payment\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Payment\Models\Payment;
use Aceraven777\PayMaya\PayMayaSDK;
use Aceraven777\PayMaya\API\Webhook;
use App\Libraries\PayMaya\User as PayMayaUser;
use Aceraven777\PayMaya\Model\Checkout\ItemAmount;
use Aceraven777\PayMaya\Model\Checkout\ItemAmountDetails;
use Aceraven777\PayMaya\API\Checkout;
use DB;
class PaymentController extends APIController
{
   	function __construct(){
   		$this->model = new Payment();
   	}

		public function checkout($data){
			PayMayaSDK::getInstance()->initCheckout(
				env('PAYMAYA_PK'),
				env('PAYMAYA_SK'),
				'SANDBOX'
			);

			$itemAmountDetails = new ItemAmountDetails();
			$itemAmountDetails->tax = "0.00";
			$itemAmountDetails->subtotal = number_format($data['amount'], 2, '.', '');
			$itemAmount = new ItemAmount();
			$itemAmount->currency = "PHP";
			$itemAmount->value = $itemAmountDetails->subtotal;
			$itemAmount->details = $itemAmountDetails;
			$params = array(
				'name' => $data['name'], 
				'amount' => $itemAmount,
				'totalAmount' => $itemAmount
			);

			$itemCheckout = new Checkout();

			$user = new PayMayaUser();
			$user->contact->phone = $data['contact_number'];
			$user->contact->email = $data['email'];

			$itemCheckout->buyer = $user->buyerInfo();
			$itemCheckout->totalAmount = $itemAmount;
			$itemCheckout->requestReferenceNumber = $data['referenceNumber'];
			$itemCheckout->items = array($params);
			$itemCheckout->redirectUrl = array(
				"success" => url($data['successUrl']),
        "failure" => url($data['failUrl']),
        "cancel" => url($data['cancelUrl']),
			);
			$exec = $itemCheckout->execute();
			if($exec == false){
				$error = $itemCheckout::getError();
				return array(
					'data' => null,
					'error' => json_encode($error)
				);
			}
			else if($exec == true){
				$parameter = array(
					'code' => $this->generateCode(),
					'account_id' => $data['account_id'],
					'details' => json_encode($exec),
					'status' => 'complete',
				);
				Payment::create($parameter);
				return array(
					'data' => $exec,
					'error' => null
				);
			};
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
