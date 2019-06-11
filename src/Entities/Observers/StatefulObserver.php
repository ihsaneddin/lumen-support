<?php
namespace Support\Entities\Observers;

use Support\Entities\Contracts\Stateful;

class StatefulObserver{

  public function saving(Stateful $model){
    if(!$model->state){
      $states = $model->obtainStates();
      if (is_array($states)){
        $default = array_keys($states)[0];
        $state_field = $model->stateAttributeField();

        foreach ($states as $state => $value) {
          if (is_array($value) && isset($value['initial']) ){
            $default = $state;
            break;
          }
        }
        if (is_null($model->{$state_field}))
          $model->{$state_field} = $default;
      }
    }
    return $model;
  }

}