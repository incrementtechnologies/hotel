<?php

namespace Increment\Imarket\Product\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class Product extends APIModel
{
    protected $table = 'products';
    protected $fillable = ['code', 'account_id', 'title',  'description', 'tags', 'price_settings', 'status'];
}
