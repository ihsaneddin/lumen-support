<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Contracts\CriteriaInterface;

use Carbon\Carbon;
use \Exception;

class BetweenTimeScope implements CriteriaInterface{

  protected $fields;
  protected $format;
  protected $request;
  protected $params= [];

  public function __construct($fields=['created_at', 'updated_at'], $format='d-m-Y', $request=null){

    $this->request =  is_null($request) ? collect(request()->all()) : $request;
    $this->format= $format;
    $this->fields = $fields;
    if (is_string($this->fields))
      $this->fields = array($this->fields);

    $this->params = $this->request->get('search');
  }

  public function apply($model, RepositoryInterface $repository){

    $class_name = $repository->model();

    foreach ($this->fields as $index => $col) {

      $field = $col;
      $format = $this->format;

      if (is_string($index)){
        $field = $index;
        $format = $col;
      }
      if(is_array($col)){
        $field = array_first(array_keys($col));
        $format = array_first($col);
      }

      if (array_get($this->params, $field)){

        $params = $this->params[$field];

        if (is_array($params)){

          if (array_get($params, 'from')){
            try{
              if(strtolower($format)  === "unix"){
                $from = Carbon::createFromTimestamp($params['from']);
              }else{
                $from = Carbon::createFromFormat($format, $params['from']);
              }
            }
            catch(Exception $e){
              try{$from = Carbon::parse($params["from"]);}
              catch(Exception $e){}
            }
            if (isset($from))
              $model = $model->where((new $class_name)->getTable(). '.' .$field, '>=', $from);
          }

          if (array_get($params, 'to')){
            try{
              if(strtolower($format)  === "unix"){
                $to = Carbon::createFromTimestamp($params['to']);
              }else{
                $to = Carbon::createFromFormat($format, $params['to']);
              }
            }
            catch(Exception $e){
              try{$from = Carbon::parse($params["to"]);}
              catch(Exception $e){}
            }
            if (isset($to))
               $model = $model->where((new $class_name)->getTable(). '.' .$field, '<=', $to);
          }

        }

      }
    }

    return $model;
  }

  public function filter($model, $class_name=null){

    if (is_null($class_name))
      $class_name = get_class($model);

    foreach ($this->fields as $index => $col) {

      $field = $col;
      $format = $this->format;

      if (is_string($index)){
        $field = $index;
        $format = $col;
      }

      if (isset($this->params[$field])){

        $params = $this->params[$field];

        if (is_array($params)){

          if (isset($params['from'])){
            try{
              $from = Carbon::createFromFormat($format, $params['from']);
            }
            catch(Exception $e){
              try{$from = Carbon::parse($params["from"]);}
              catch(Exception $e){}
            }
            if (isset($from))
              $model = $model->where((new $class_name)->getTable(). '.' .$field, '>=', $from);
          }

          if (isset($params['to'])){
            try{
              $to = Carbon::createFromFormat($format, $params['to']);
            }
            catch(Exception $e){
              try{$from = Carbon::parse($params["to"]);}
              catch(Exception $e){}
            }
            if (isset($to))
               $model = $model->where((new $class_name)->getTable(). '.' .$field, '<=', $to);
          }

        }

      }
    }

    return $model;
  }

}