<?php 
namespace Support\Entities;

use Support\Entities\Traits\UuidTrait;
use Support\Entities\Traits\CacheableTrait;

use Illuminate\Database\Eloquent\Model as Base;
use Illuminate\Database\Eloquent\SoftDeletes;


abstract class Model extends Base{

  #use soft delete
  use SoftDeletes;

  #use cache
  use CacheableTrait;

  #use uuid as primary key for this abstract model class
  use UuidTrait;

  #helper methods

  public function wasDifferentFromOriginal(string $attribute){
    $attributes = $this->getAttributes();
    $current_value = array_get($attributes, $attribute);
    return $this->getOriginal($attribute) != $current_value;
  }

  public function isA($types = []){
    if(!is_array($types) && !is_string($types)){
      return;
    }
    if(!is_array($types)){
      $types = array($types);
    }
    $bool = false;
    foreach ($types as $type) {
      $bool = snake_case(class_basename($this->type)) == $type;
      if($bool){
        break;
      }
    }
    return $bool;
  }

}