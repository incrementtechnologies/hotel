<?php

namespace Increment\Hotel\Room\Models;

use Illuminate\Database\Eloquent\Model;
use App\APIModel;

class RoomPriceStatus extends APIModel
{
    protected $table='room_price_status';
    protected $fillable=['price_id', 'category_id', 'amount', 'qty',  'status'];
}
