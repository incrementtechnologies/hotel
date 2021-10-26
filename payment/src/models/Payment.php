<?php

namespace Increment\Imarket\Payment\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Payment extends APIModel
{
    protected $table = 'coupons';
    protected $fillable = ['account_id', 'code', 'method', 'details', 'status'];

}
