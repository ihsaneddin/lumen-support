<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Contracts\CriteriaInterface;

use Carbon\Carbon;
use \Exception;

class BetweenNumberScope implements CriteriaInterface{

  protected $fields;
  protected $format;
  protected $request;
  protected $params= [];

  public function __construct($fields=[], $format='int'){
    $this->request = request();
    $this->format= $format;
    $this->fields = $fields;

    if (is_string($this->fields))
      $this->fields = array($this->fields);

    $this->params = $this->request->get('search');
  }

  public function apply($model, RepositoryInterface $repository){
    $query = $model;
    try{
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
                $from = $this->format_value($format, $params['from']);
                $model = $model->where($field, '>=', $from);
              }
              catch(Exception $e){}
            }

            if (array_get($params, 'to')){
              $to = $this->format_value($format, $params['to']);
              $model = $model->where($field, '<=', $to);
            }

          }

        }
      }
    }catch(Exception $e){
      return $query;
    }
    return $model;
  }

  protected function format_value($format, $value){
    switch ($format) {
      case 'int':
        $value =  (int) $value;
        break;
      case 'float':
        $value= (float) $value;
        break;
    }
    return $value;
  }

}