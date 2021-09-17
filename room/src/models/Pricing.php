<?php


namespace Increment\Imarket\Product\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class Pricing extends APIModel
{
    protected $table = 'pricings';
    protected $fillable = ['account_id', 'product_id', 'minimum', 'maximum', 'price'];
}
