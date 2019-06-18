<?php
namespace Support\Helpers;

use Support\Workers\LogSlackWorker;

use Illuminate\Support\Facades\Log;

class LogSlack
{
  public function __call($name, $arguments)
  {
    if(app()->environment(["staging", "production"])){
      dispatch(new LogSlackWorker($name, $arguments));
    }
  }
}
