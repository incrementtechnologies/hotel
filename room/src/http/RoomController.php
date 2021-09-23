<?php

namespace Increment\Hotel\Room\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Hotel\Room\Models\Room;
use Carbon\Carbon;
class RoomController extends APIController
{
  function __construct(){
    $this->model = new Room;
  }
}
