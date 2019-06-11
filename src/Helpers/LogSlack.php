<?php
/**
 * Created by PhpStorm.
 * User: billy
 * Date: 11/04/19
 * Time: 14:34
 */

namespace Support\Helpers;


use Jobs\LogQueueJob;
use Illuminate\Support\Facades\Log;

class LogSlack
{
  public function __call($name, $arguments)
  {
    if(app()->environment(["staging", "production"])){
      dispatch(new LogQueueJob($name, $arguments));
    }
  }
}
