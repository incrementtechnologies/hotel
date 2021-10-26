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
           'label'
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
}
