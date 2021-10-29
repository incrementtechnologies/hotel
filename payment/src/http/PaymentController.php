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
			$this->setUpWebHooks();
   	}
		
		public function setUpWebHooks(){
			PayMayaSDK::getInstance()->initCheckout(
				env('PAYMAYA_PK'),
				env('PAYMAYA_SK'),
				'SANDBOX'
			);
			$this->clearWebhooks();
			$successWebhook = new Webhook();
			$successWebhook->name = Webhook::CHECKOUT_SUCCESS;
			$successWebhook->callbackUrl = url(env('APP_URL').'/payments/callback');
			$successWebhook->register();

			$failureWebhook = new Webhook();
			$failureWebhook->name = Webhook::CHECKOUT_FAILURE;
			$failureWebhook->callbackUrl = url('callback/error');
			$failureWebhook->register();

			$dropoutWebhook = new Webhook();
			$dropoutWebhook->name = Webhook::CHECKOUT_DROPOUT;
			$dropoutWebhook->callbackUrl = url('callback/dropout');
			$dropoutWebhook->register();
		}

		public function clearWebhooks(){
			$webhooks = Webhook::retrieve();
			foreach ($webhooks as $webhook) {
					$webhook->delete();
			}
		}

		public function checkout($data){
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
					'payload' => $data['payload'],
					'payload_value' => $data['payload_value'],
					'details' => json_encode($exec),
					'status' => 'pending',
				);
				Payment::create($parameter);
				return array(
					'data' => $exec,
					'error' => null
				);
			};
		}

		public function callback(Request $request){
			$data = $request->all();
			$temp = Payment::where('payload_value', '=', $data['id'])->orderBy('created_at', '=', 'desc')->first();
			$checkout = json_decode($temp['details']);
			$transaction_id = $checkout->checkoutId;
			if (! $transaction_id) {
					return ['paymentStatus' => false, 'message' => 'Transaction Id Missing'];
			}
			$itemCheckout = new Checkout();
			$itemCheckout->id = $transaction_id;
			$checkout = $itemCheckout->retrieve();
			//update reservation
			$params = array(
				'id' => $data['id'],
				'payment_method'=> 'credit',
				'status' => trtolower($checkout['paymentStatus']) === 'payment_success' ? 'completed' : 'failed'
			);
			app('Increment\Hotel\Reservation\Http\ReservationController')->updateReservationCart($params);
			//======End=========
	

			$result = Payment::where('details', 'like', '%'.$transaction_id.'%')->update(array(
				'status' => strtolower($checkout['paymentStatus'])
			));
			$this->response['data'] = $result;
			return $this->response();
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
