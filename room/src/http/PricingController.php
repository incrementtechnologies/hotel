<?php
namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Pricing;
use Increment\Hotel\Room\Models\Room;
use Carbon\Carbon;
class PricingController extends APIController
{
    function __construct(){
    	$this->model = new Pricing();
        $this->notRequired = array(
           'label', 'refundable'
        );
    }

    public function retrieve(Request $request){
    	$data = $request->all();
    	$this->retrieveDB($data);
    	$result = $this->response['data'];
    	if(sizeof($result) > 0){
    		$i = 0;
    		foreach ($result as $key) {
    			$this->response['data'][$i]['room'] = $this->getProduct($result[$i]['room_id']);
    			$i++;
    		}
    	}
    	return $this->response();
    }

		public function create(Request $request){
			$data = $request->all();
			if(isset($data['addOnPrice'])){
				$data['regular'] += array_sum($data['addOnPrice']);
			}
			$this->insertDB($data);
			if($this->response['data']){
				$priceId = Pricing::leftJoin('rooms as T1', 'T1.id', '=', 'pricings.room_id')->where('pricings.id', '=', $this->response['data'])->first();
				$prices = Pricing::leftJoin('rooms as T1', 'T1.id', '=', 'pricings.room_id')->where('pricings.regular', '=', $priceId['regular'])->where('pricings.label', '=', $priceId['label'])->where('T1.category', '=', $priceId['category'])->get();
				
				$params = array(
					'price_id' => $this->response['data'],
					'category_id' => $data['category'],
					'amount' => $data['regular'],
					'refundable' => $data['refundable'],
					'qty' => 1,
					'status' => 'available'
				);
				app('Increment\Hotel\Room\Http\RoomPriceStatusController')->insertPriceStatus($params);
			}
			return $this->response();
		}

		public function retrievePricings(Request $request){
			$data = $request->all();
			$con = $data['condition'];
			$result = Pricing::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])->groupBy('label')->get();
			$this->response['data'] = $result;
			return $this->response();
		}
    public function getProduct($productId){
    	$result = Room::where('id', '=', $productId)->first();
    	return ($result) ? $result : null;
    }

    public function getPrice($id){
      $result = Pricing::where('product_id', '=', $id)->orderBy('minimum', 'asc')->get();
      return (sizeof($result) > 0) ? $result : null;
    }

		public function retrieveMaxMin(){
			$min = Pricing::where('deleted_at', '=', null)->orderBy('regular', 'asc')->first();
			$max = Pricing::where('deleted_at', '=', null)->orderBy('regular', 'desc')->first();
			return array(
				'min' => $min !== null ? (int)$min['regular'] : 0,
				'max' => $max !== null ? (int)$max['regular'] : 0
			);
		}

		public function retrieveLabel(){
			$withTax = Pricing::groupBy('label')->where('tax', '=', 1)->get(['id', 'label']);
			$withoutTax = Pricing::groupBy('label')->where('tax', '=', 0)->get(['id', 'label']);
			$tempArray = array();
			if(sizeof($withTax) > 0){
				for ($i=0; $i <= sizeof($withTax)-1; $i++) {
					$item = $withTax[$i];
					$item['label'] = $item['label'].' '.'with tax & fees';	
					array_push($tempArray, $item);
				}
			}
			if(sizeof($withoutTax) > 0){
				for ($i=0; $i <= sizeof($withoutTax)-1; $i++){
					$item = $withoutTax[$i];
					array_push($tempArray, $item);
				}
			}
			return $tempArray;
		}

		public function update(Request $request){
			$data = $request->all();
			$params = array(
				'account_id' => $data['account_id'],
				'room_id' => $data['room_id'],
				'regular' => $data['regular'],
				'refundable' => $data['refundable'],
				'currency' => $data['currency'],
				'tax' => $data['tax'],
				'label' => $data['label'],
				'updated_at' => Carbon::now()
			);
			$result = Pricing::where('id', '=', $data['id'])->update($params);
			if($result){
				$condition = array(
					array('price_id', '=', $data['id'])
				);
				$update = array(
					'amount' => $data['regular'],
					'category_id' => $data['category_id']
				);
				$updatePriceStatus = app('Increment\Hotel\Room\Http\RoomPriceStatusController')->updateByParams($condition, $update);
			}
			$this->response['data'] = $result === 1 ? true : false;
			return $this->response();
		}

		public function retrieveByColumn($column, $value){
			return Pricing::where($column, '=', $value)->first();
		}

		public function deleteByColumn($column, $value){
			return Pricing::where($column, '=', $value)->update(array(
				'deleted_at' => Carbon::now()
			));
		}
}
