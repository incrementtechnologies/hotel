<?php

namespace Increment\Hotel\Room\Models;

use App\APIModel;
use Illuminate\Database\Eloquent\Model;

class Cart extends APIModel
{
    //
    protected $table = 'carts';
    protected $fillable = ['account_id', 'category_id', 'price_id', 'reservation_id', 'status', 'qty',  'check_in', 'check_out', 'details'];
}
