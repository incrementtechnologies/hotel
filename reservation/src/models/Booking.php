<?php

namespace Increment\Hotel\Reservation\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Booking extends APIModel
{
    protected $table = 'bookings';
    protected $fillable = ['reservation_id','room_id', 'room_type_id'];
}
