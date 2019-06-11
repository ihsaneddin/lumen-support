<?php 
namespace Support\Entities\Traits;

use Support\Entities\Contracts\Stateful;
use Support\Entities\Observers\StatefulObserver;

use Acacha\Stateful\Traits\StatefulTrait as Base;

trait StatefulTrait{

  use Base;

  public static function bootStatefulTrait(){
    static::observe(app(StatefulObserver::class));
  }

  public function atInitialState(){
    $state_field = $this->stateAttributeField();
    if (is_null($this->{$state_field}))
      return true;

    $initial_state = null;
    foreach($this->states as $state => $option){
      if (is_array($option)){
        if (isset($option['initial']) && $option['initial']){
          $initial_state = $state;
          break;
        }
      }
    }
    return ($this->state == $initial_state);
  }

  public function stateAttributeField(){
    if (isset(self::$__state_field)){
      return self::$__state_field;
    }else{
      return 'state';
    }
  }

  public static function scopeWithState($query, $state='active'){
    $table_name = (new static)->table;
    return $query->where($table_name.'.state', $state);
  }

  public function getReadableStateAttribute(){
    return title_case(join(' ', explode('_', $this->state)));
  }

  public function getIsStateChangedAttribute(){
    array_get($this->getOriginal(), $this->stateAttributeField()) != $this->state;
  }

  public function could(string $transition){
    return $this->canPerformTransition($transition);
  }

  public function scopeWithStates($query, $states = []){
    if (!is_array($states)){
      $states = array($states);
    }
    $states = array_filter($states);
    if (empty($states)){
      return $query;
    }else{
      $table_name = (new static)->table;
      return $query->whereIn($table_name.'.state', $states);
    }
  }

  public static function arrayOfReadableStates(){
    $model = new static();
    $states = $model->states;
    $readable_states = [];
    foreach($states as $key => $state){
      if(is_numeric($key)){
        $readable_states[$state] = title_case(join(' ', explode('_', $state)));
      }else{
        $readable_states[$key] = title_case(join(' ', explode('_', $key)));
      }
    }
    return $readable_states;
  }

  public static function getStates(){
    $model = new static();
    $states = $model->states;
    $normal_states = [];
    foreach($states as $key => $state){
      if(is_numeric($key)){
        $normal_states[] = $state;
      }else{
        $normal_states[] = $key;
      }
    }
    return $normal_states;
  }

  public function isInStates($states){
    if (!is_array($states)){
      $states = array($states);
    }
    foreach($states as $state){
      if ($this->state == $state){
        return true;
      }
    }
    return false;
  }

  public function isNotInStates($states){
    if (!is_array($states)){
      $states = array($states);
    }
    foreach($states as $state){
      if ($this->state == $state){
        return false;
      }
    }
    return true;
  }

}