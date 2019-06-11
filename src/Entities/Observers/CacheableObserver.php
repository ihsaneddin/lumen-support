<?php
namespace Support\Entities\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Cache;

class CacheableObserver {

  public function created(Model $model){
    $this->clear_cache($model);
    return $model;
  }

  public function updated(Model $model){
    $this->clear_cache($model);
    return $model;
  }

  public function saved(Model $model){
    $this->clear_cache($model);
    return $model;
  }

  public function deleted(Model $model){
    $this->clear_cache($model);
    return $model;
  }

  protected function clear_cache($model){
    $model->clear_cache();
    $class = get_class($model);
    $class::_clear_cache();
  }


}