<?php
namespace Support\Facades;

use Support\Helpers\LogSlack as LogSlackBase;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

class LogSlack extends Facade
{
  protected static function getFacadeAccessor() { return LogSlackBase::class; }
}
