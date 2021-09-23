<?php

namespace Increment\Hotel\Room\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class Room extends APIModel
{
    protected $table = 'rooms';
    protected $fillable = ['code', 'account_id', 'title',  'description', 'category', 'status'];
}
