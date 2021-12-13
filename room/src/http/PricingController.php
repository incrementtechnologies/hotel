<?php


namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Pricing;
use Increment\Hotel\Room\Models\Room;
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
			$this->insertDB($data);
			if($this->response['data']){
				$priceId = Pricing::leftJoin('rooms as T1', 'T1.id', '=', 'pricings.room_id')->where('pricings.id', '=', $this->response['data'])->first();
				$prices = Pricing::leftJoin('rooms as T1', 'T1.id', '=', 'pricings.room_id')->where('pricings.regular', '=', $priceId['regular'])->where('pricings.label', '=', $priceId['label'])->where('T1.category', '=', $priceId['ategory']);
				$checkIfExist = array(
					array('amount', '=', $priceId['regular'])
				);
				$exist = 	app('Increment\Hotel\Room\Http\RoomPriceStatusController')->insertPriceStatus($checkIfExist);
				if($exist){
					$params = array(
						'price_id' => $this->response['data'],
						'category_id' => $data['category'],
						'amount' => $data['regular'],
						'qty' => 0,
						'status' => 'available'
					);
					app('Increment\Hotel\Room\Http\RoomPriceStatusController')->insertPriceStatus($params);
				}
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

		public function retrieveMaxMin(Request $request){
			$data=$request->all();
			$min = Pricing::where('deleted_at', '=', null)->orderBy('regular', 'asc')->first();
			$max = Pricing::where('deleted_at', '=', null)->orderBy('regular', 'desc')->first();
			$this->response['data'] = array(
				'min' => $min !== null ? $min['regular'] : 0,
				'max' => $max !== null ? $max['regular'] : 0
			);
			return $this->response();
		}
}
