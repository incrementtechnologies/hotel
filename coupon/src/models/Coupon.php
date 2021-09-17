<?php

namespace Increment\Imarket\Coupon\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Coupon extends APIModel
{
    protected $table = 'coupons';
    protected $fillable = ['account_id', 'code', 'description', 'type', 'value', 'start', 'end'];

    protected $dates = ['start', 'end'];
    protected $dateFormat = 'Y-m-d H:i:s';
    // public function getStartAttribute($date)
    // {
    //   return Carbon::createFromFormat('Y-m-d H:i:s', $date)->copy()->tz('Asia/Manila')->format('F j, Y g:i A');
    // }
    // public function getEndAttribute($date)
    // {
    //   return Carbon::createFromFormat('Y-m-d H:i:s', $date)->copy()->tz('Asia/Manila')->format('F j, Y g:i A');
    // }
}
