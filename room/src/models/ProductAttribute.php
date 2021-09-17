<?php

namespace Increment\Imarket\Product\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class ProductAttribute extends APIModel
{
    protected $table = 'product_attributes';
    protected $fillable = ['account_id', 'product_id', 'payload',  'payload_value', 'price', 'currency'];
}
