<?php
namespace Support\Entities\Observers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UuidObserver{

  public function creating(Model $model){
    if($model->uuid_enabled){
      $uuid_field = $model->uuid_column;
      if (!$model->{$uuid_field}) {
          $model->{$uuid_field} = strtoupper(Uuid::uuid4()->toString());
      }
    }
    return $model;
  }

  // public function saving(Model $model){
  //   if($model->uuid_enabled){
  //     $uuid_field = $model->uuid_column;
  //     if(is_null($model->{$uuid_field})){
  //       $model = $this->creating($model);
  //     }else{
  //       $original_uuid = $model->getOriginal($uuid_field);
  //       if ($original_uuid !== $model->{$uuid_field}) {
  //         $model->{$uuid_field} = $original_uuid;
  //       }
  //     }
  //   }
  //   return $model;
  // }

}