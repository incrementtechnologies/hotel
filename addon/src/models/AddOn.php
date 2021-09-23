<?php

namespace Increment\Hotel\AddOn\Models;

use Illuminate\Database\Eloquent\Model;
use App\APIModel;

class AddOn extends APIModel
{
    protected $table = 'add_ons';
    protected $fillable = ['account_id', 'title', 'price', 'type'];
    // protected $fillable = ['merchant_id', 'account_id', 'title',  'currency', 'price', 'url'];
}
