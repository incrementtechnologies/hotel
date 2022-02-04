<?php

namespace Increment\Hotel\Reservation\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Reservation extends APIModel
{
    protected $table = 'reservations';
    protected $fillable = ['reservation_code', 'account_id','code', 'merchant_id', 'payload', 'payload_value', 'details', 'status'];
}
