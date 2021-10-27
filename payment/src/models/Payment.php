<?php

namespace Increment\Hotel\Payment\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
use Carbon\Carbon;
class Payment extends APIModel
{
    protected $table = 'payments';
    protected $fillable = ['account_id', 'code', 'method', 'details', 'status'];

}
