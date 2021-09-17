<?php

namespace Increment\Imarket\Reservation\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Reservation extends APIModel
{
    protected $table = 'reservations';
    protected $fillable = ['account_id','code', 'merchant_id', 'payload', 'payload_value', 'details', 'check_in', 'check_out', 'status'];
}
