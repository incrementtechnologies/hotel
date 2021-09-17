<?php

namespace Increment\Hotel\Room\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\APIModel;
class ProductImage extends APIModel
{
    protected $table = 'product_images';
    protected $fillable = ['room_id', 'url', 'status'];
}