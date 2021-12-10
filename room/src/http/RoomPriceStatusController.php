<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use  Increment\Hotel\Room\Models\RoomPriceStatus;
use Carbon\Carbon;

class RoomPriceStatusController extends APIController
{
    public function __construct(){
        $this->model = new RoomPriceStatus();
    }

    public function insertPriceStatus($data){
        return $this->insertDB($data);
    }

    public function checkIfPriceExist($params){
        return RoomPriceStatus::where($condition)->get();
    }

    public function updateQtyById($id, $value){
        return RoomPriceStatus::where('id', '=', $id)->update(array(
            'qty' => $value,
            'updated_at' => Carbon::now()
        ));
    }
    
    public function updateQtyByPriceId($priceId, $categoryId, $value){
        return RoomPriceStatus::where('price_id', '=', $priceId)->where('category_id', '=', $categoryId)->update(array(
            'qty' => $value,
            'updated_at' => Carbon::now()
        ));
    }
}
