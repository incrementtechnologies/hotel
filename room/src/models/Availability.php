<?php

namespace Increment\Hotel\Room\Models;

use Illuminate\Database\Eloquent\Model;
use App\APIModel;

class Availability extends APIModel
{
    protected $table = 'availabilities';
    protected $fillable = ['payload', 'payload_value', 'start_date', 'end_date', 'status'];
}
