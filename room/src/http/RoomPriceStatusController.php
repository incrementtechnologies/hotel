<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use  Increment\Hotel\Room\Models\RoomPriceStatus;

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
            'qty' => $qty,
            'updated_at' => $value
        ));
    }
}
