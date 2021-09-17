<?php

namespace Increment\Hotel\AddOn\Models;

use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    protected $table = 'add-ons';
    protected $fillable = ['merchant_id', 'account_id', 'title',  'currency', 'price', 'url'];
}
