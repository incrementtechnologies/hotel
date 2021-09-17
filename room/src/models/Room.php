<?php

namespace Increment\Hotel\Room\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class Room extends APIModel
{
    protected $table = 'products';
    protected $fillable = ['code', 'account_id', 'title',  'description', 'category', 'status'];
}
