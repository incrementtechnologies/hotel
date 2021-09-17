<?php


namespace Increment\Imarket\Product\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class ProductExclusiveLocation extends APIModel
{
    protected $table = 'product_exclusive_locations';
    protected $fillable = ['product_id', 'locality', 'region', 'country'];
}
