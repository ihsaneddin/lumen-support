<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class OrderByScope implements CriteriaInterface
{

  public function apply($model, RepositoryInterface $repository)
  {
    $sorts = request()->sort;

    if($sorts){
      foreach ($sorts as $field => $sort){
        if($sort && !empty($sort)){
          if(strpos($field, '.')){
            list($relation, $fieldName) = explode(".",$field);
            $model->whereHas($relation, function ($modelRelation) use ($fieldName, $sort){
             return $modelRelation->orderBy($fieldName, strtolower($sort));
            });
          }else{
            return $model->orderBy($field, strtolower($sort));
          }
        }

      }
    }

      return $model;
  }

}