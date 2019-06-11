<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class ScopedByMorphRelation implements CriteriaInterface
{

  protected $morphs = [];
  protected $request;

  public function __construct(array $morphs =[]){
    $this->morphs = $morphs;
    $this->request = request();
  }

  public function apply($model, RepositoryInterface $repository)
  {
    $morphs = $this->morphs;
    foreach ($morphs as $index => $morph) {
      if(is_array($morph)){
        if(is_numeric($index) && is_array($morph)){
          if(isset($morph["type_field"]) && isset($morph["type"])){
            $model = $model->where($morph["type_field"], $morph["type"]);
          }
          if(isset($morph["key_field"]) && isset($morph["key"])){
            if($this->request->get($morph['key'])){
              $model = $model->where($morph["key_field"], $this->request->get($morph['key']));
            }elseif($this->request->route($morph['key'])){
              $model = $model->where($morph["key_field"], $this->request->route($morph['key']));
            }else{
              $model = $model->where($morph["key_field"], $morph['key']);
            }
          }
        }
      }
    }
    return $model;
  }

}