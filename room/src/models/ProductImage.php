<?php

namespace Increment\Imarket\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\APIModel;
class ProductImage extends APIModel
{
    protected $table = 'product_images';
    protected $fillable = ['account_id', 'url', 'status'];
}