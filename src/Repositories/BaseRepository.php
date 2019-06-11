<?php
namespace Support\Repositories;

use Support\Repositories\Criterias\RequestCriteria;
use Support\Repositories\Scopes\BetweenTimeScope;
use Support\Repositories\Contracts\BaseRepositoryInterface;
use Support\Repositories\Validators\LaravelValidator;
use Support\Helpers\MessageBag;

use Acacha\Stateful\Contracts\Stateful;
use Closure;
use Exception;
use Illuminate\Container\Container as Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
//use Illuminate\Support\MessageBag;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\Presentable;
use Prettus\Repository\Contracts\PresenterInterface;
use Prettus\Repository\Contracts\RepositoryCriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Events\RepositoryEntityCreated;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Repository\Traits\ComparesVersionsTrait;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Prettus\Repository\Eloquent\BaseRepository as Base;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;
use Prettus\Repository\Events\RepositoryEntityUpdated;
use Prettus\Repository\Events\RepositoryEntityDeleted;

/**
 * Class UserRepositoryEloquent
 * @package namespace Modules\Repositories;
 */
abstract class BaseRepository extends Base implements BaseRepositoryInterface#, CacheableInterface
{

  #use CacheableRepository;

  protected $model_object;
  protected $stateful_model=false;
  protected $file_attributes = array();

  public function __construct(Application $app)
  {
    parent::__construct($app);
    $model_class = $this->model();
    $this->model_object = new $this->model;
    $this->stateful_model = $this->model_object instanceOf Stateful;
  }

  public function validator(array $params= array(), $id = null, $root = null){
    $validator = app(LaravelValidator::class);

    if ($validator instanceof ValidatorInterface) {
      if (method_exists($this, 'rules')){

        $reflector = new \ReflectionMethod($this, 'rules');
        $parameters = collect($reflector->getParameters());
        if ($parameters->count() == 1){
          $rules = $this->rules($params);
        }elseif($parameters->count() == 2){
          $rules = $this->rules($params, $id);
        }
        elseif($parameters->count() == 3){
          $rules = $this->rules($params, $id, $root);
        }

        if (is_array($rules)){
          $validator->setRules($rules);
        }
      }else{
        if (is_array($this->rules)){
          $validator->setRules($this->rules);
        }
      }

      if(property_exists($this, 'custom_validation_messages') && is_array($this->custom_validation_messages)){
        $validator->setCustomMessages($this->custom_validation_messages);
      }

      if(method_exists($this, "customValidationMessages")){
        $custom_validation_messages = $this->customValidationMessages();
        if(is_array($custom_validation_messages)){
          $validator->setMessages($custom_validation_messages);
        }
      }

    }

    return $validator;
  }

  public function __call($name, $args){
    if( $this->stateful_model && in_array($name, array_keys($this->model_object->obtainTransitions())) ) {
      if (empty($args)){
        return  call_user_func( array($this, $name) );
      }else{
        return  call_user_func_array( array($this, 'transit'), [$name, $args[0]] );
      }
    }
    else{
      if (method_exists($this, $name)){
        parent::__call($name, $args);
      }else{
        trigger_error("Call to undefined method '{$name}'");
      }

      //
    }
  }

  protected function stateful_model(){
    return $this->model_object instanceOf Stateful;
  }

  protected function is_transition_exist($transition){
    if ($this->stateful_model()){
      $transitions = array_keys($this->model_object->obtainTransitions());
      if (in_array($transition, $transitions)){
         return true;
      }
    }
  }

  protected function transit($transition, $id){
    if ($this->is_transition_exist($transition)){
      $this->applyScope();

      $temporarySkipPresenter = $this->skipPresenter;

      $this->skipPresenter(true);

      $model = $this->model->findOrFail($id);
      $model->{$transition}();

      $this->skipPresenter($temporarySkipPresenter);
      $this->resetModel();

      event(new RepositoryEntityUpdated($this, $model));

      return $this->parserResult($model);
    }
  }


  public function model()
  {
    return null;
  }

  /**
  * Boot up the repository, pushing criteria
  */
  public function boot()
  {
    $this->pushCriteria(app(RequestCriteria::class));
    $this->pushCriteria(BetweenTimeScope::class);
    //$this->pushCriteria(app('Repositories\RequestCriteria'));
  }

  public function restore($id){
    if (is_callable( array($this->model, "onlyTrashed") )){
      $this->applyScope();

      $model = $this->model->onlyTrashed()->findOrFail($id);
      $model->restore();
      $this->resetModel();
      event(new RepositoryEntityUpdated($this, $model));
      return $this->parserResult($model);
    }
  }

  public function with_trashed(){
    $this->model = $this->model->withTrashed();
    return $this;
  }

  public function only_thrashed(){
    $this->model = $this->model->onlyTrashed();
    return $this;
  }

  public function without_trashed(){
    $this->model = $this->model->withoutTrashed();
    return $this;
  }

  public function count() {

      $this->applyCriteria();
      $this->applyScope();

      $result = $this->model->count();

      $this->resetModel();
      $this->resetScope();

      return $result;

  }

  public function delete($id)
  {
    $this->applyScope();

    $temporarySkipPresenter = $this->skipPresenter;
    $this->skipPresenter(true);

    $model = $this->find($id);
    $originalModel = clone $model;

    $this->skipPresenter($temporarySkipPresenter);
    $this->resetModel();

    $deleted = $model->delete();

    event(new RepositoryEntityDeleted($this, $originalModel));

    return $model;
    //return $deleted;
  }

  #implements nested attributes support

  #
  # static property for store nested attributes, format :
  #  ["{name_of_the_relation}" => ["repository_name" => "{repositroy class}", "param_name" => "{param key ins   tead of [name_of_the_relation]}_attributes", "attributes" => ["{all the attributes name}", "allow_destroy" => false]]]
  #
  protected static $accept_nested_attributes_for = array();
  protected $errors = array();

  protected function resetErrors(){
    $this->errors = array();
  }

  protected function isErrorsPresent(){
    return !empty($this->errors);
  }

  protected function getNestedAttributesOptionOf(string $relation, string $key){
    $options = $this->getAcceptNestedAttributesFor();

    if (is_array($options)){
      $option = array_get($options, $relation. '.' .$key);
      if (is_null($option)){
        switch ($key) {
          case 'repository_name':
              return $relation.'RepositoryEloquent';
            break;
          case 'param_name' :
              return snake_case($relation). '_attributes';
            break;
          case 'allow_destroy' :
              return false;
            break;
          case 'allow_blank' :
            return true;
          default:
            return array();
          break;
        }
      }else{
        return $option;
      }
    }

  }

  protected function getAcceptNestedAttributesFor(){
    if (method_exists($this, 'acceptNestedAttributesFor')){
      return $this->getAcceptNestedAttributesFor();
    }else{
      return static::$accept_nested_attributes_for;
    }
  }

  protected function getAcceptedNestedAttributesParamKeys(){
    return collect(array_keys($this->getAcceptNestedAttributesFor()))->map(function($relation_name){ return $this->getNestedAttributesOptionOf($relation_name, 'param_name'); })->all();
  }

  protected function saveNestedAttributes($model, $relation_name, array $attributes){
    $attributes = collect($attributes)->only($this->getNestedAttributesOptionOf($relation_name, 'attributes'))->all();
    if(!$this->getNestedAttributesOptionOf($relation_name, 'allow_blank')){
      $nattributes = $attributes;
      if(empty(array_filter($attributes))){
        if(method_exists($this, "customValidationMessages")){
          $messages = $this->customValidationMessages();
          if(isset($messages[$relation_name.".required"])){
            return new MessageBag([$relation_name => $messages[$relation_name.".required"]]);
          }
        }
        return new MessageBag([$relation_name => "Can't be blank"]);
      }
    }

    $repository = $this->app->make($this->getNestedAttributesOptionOf($relation_name, 'repository_name'));

    $instance = new $repository->model();

    if ($model->$relation_name() instanceOf HasOne){
      $attributes[$model->$relation_name()->getForeignKeyName()] = $model->getKey();
      if (isset($attributes[$instance->getKeyName])){
        $instance = $repository->findWhere([$model->$relation_name()->getForeignKeyName() => $model->getKey(), $instance->getKeyName() => array_get($attributes, $instance->getKeyName()) ])->first();
      }
    }

    if ($model->$relation_name() instanceOf MorphOne){
      $attributes[$model->$relation_name()->getMorphType()] = get_class($model);
      $attributes[$model->$relation_name()->getForeignKeyName()] = $model->getKey();
      if (isset($attributes[$instance->getKeyName()])){
        $instance = $repository->findWhere([$model->$relation_name()->getForeignKeyName() => $model->getKey(), $model->$relation_name()->getMorphType() => get_class($model), $instance->getKeyName() => array_get($attributes, $instance->getKeyName()) ])->first();
      }
    }

    if (empty($instance)){
      return new MessageBag(['primary_key' => "Not found"]);
    }

    if ($instance->exists){
      if ($this->getNestedAttributesOptionOf($relation_name, 'allow_destroy') && (bool)array_get($attributes, "_destroy", false) ){
        return $repository->delete($instance->getKey());
      }
      return $repository->update($attributes, $instance->getKey(), true, $model);
    }else{
      if (($this->getNestedAttributesOptionOf($relation_name, 'allow_destroy') && !array_get($attributes, "_destroy", false)) || !$this->getNestedAttributesOptionOf($relation_name, 'allow_destroy')){
        return $repository->create($attributes, true, $model);
      }
    }
  }

  protected function saveManyNestedAttributes($model, string $relation_name, array $attributes){

    $attributes = collect($attributes)->only($this->getNestedAttributesOptionOf($relation_name, 'attributes'))->all();

    $repository = $this->app->make($this->getNestedAttributesOptionOf($relation_name, 'repository_name'));

    $instance = new $repository->model();

    if ($model->$relation_name() instanceOf HasMany){
      $attributes[$model->$relation_name()->getForeignKeyName()] = $model->getKey();
      if (isset($attributes[$instance->getKeyName()])){
        $instance = $repository->findWhere([$model->$relation_name()->getForeignKeyName() => $model->getKey(), $instance->getKeyName() => array_get($attributes, $instance->getKeyName()) ])->first();
      }
    }

    if ($model->$relation_name() instanceOf MorphMany){
      $attributes[$model->$relation_name()->getMorphType()] = get_class($model);
      $attributes[$model->$relation_name()->getForeignKeyName()] = $model->getKey();
      if (isset($attributes[$instance->getKeyName()])){
        $instance = $repository->findWhere([$model->$relation_name()->getForeignKeyName() => $model->getKey(), $model->$relation_name()->getMorphType() => get_class($model), $instance->getKeyName() => array_get($attributes, $instance->getKeyName()) ])->first();
      }
    }

    if (empty($instance)){
      return new MessageBag(['primary_key' => "Not found"]);
    }
    if ($instance->exists){
      if ($this->getNestedAttributesOptionOf($relation_name, 'allow_destroy') && (bool) array_get($attributes, "_destroy", false) ){
        return $repository->delete($instance->getKey());
      }
      return $repository->update($attributes, $instance->getKey(), true, $model);
    }else{
      if (($this->getNestedAttributesOptionOf($relation_name, 'allow_destroy') && !(bool)array_get($attributes, "_destroy", false)) || !(bool)$this->getNestedAttributesOptionOf($relation_name, 'allow_destroy')){
        return $repository->create($attributes, true, $model);
      }
    }

  }

  public function create(array $attributes, bool $transaction_started = false, $root = null){

    if (!$transaction_started && !$root){
      DB::beginTransaction();
      $transaction_started = true;
    }

    $this->resetErrors();

    #first we extract nested attributes params
    $nested_attributes = collect($attributes)->only($this->getAcceptedNestedAttributesParamKeys())->all();
    $attributes = collect($attributes)->except($this->getAcceptedNestedAttributesParamKeys())->all();

    #save record
    $mixed = array();

    if (!is_null($this->validator)) {
      // we should pass data that has been casts by the model
      // to make sure data type are same because validator may need to use
      // this data to compare with data that fetch from database.
      if( $this->versionCompare($this->app->version(), "5.2.*", ">") ){
          $mixed = $this->model->newInstance()->forceFill($attributes)->makeVisible($this->model->getHidden())->toArray();
      }else{
          $model = $this->model->newInstance()->forceFill($attributes);
          $model->addVisible($this->model->getHidden());
          $mixed = $model->toArray();
      }

      #just insert file attributes for $attributes becase model#toArray not return it, fuckit
      if (isset($this->file_attributes) && is_array($this->file_attributes)){
        foreach ($this->file_attributes as $file_key) {
          if (isset($attributes[$file_key])){
            $mixed[$file_key] = $attributes[$file_key];
          }
        }
      }

      if (method_exists($this, 'rules')){
        $this->makeValidator($this->validator($mixed, null, $root));
      }

      #we can not raise exception here as it will break nested attributes saving process
      try{
        $this->validator->with($mixed)->passesOrFail(ValidatorInterface::RULE_CREATE);
      }catch(ValidatorException $e){
        $this->errors = $e->getMessageBag()->messages();
      }
    }

    //$attributes = array_merge($mixed, $attributes);

    $mixed = $this->beforeCreate($mixed);
    $mixed = $this->beforeSave($mixed);

    $mixed = array_filter($mixed, function($attribute){ return !is_null($attribute); });
    $model = $this->model->newInstance($mixed);

    if (!$this->isErrorsPresent()){
      $model->save();
    }

    if (!empty($nested_attributes)){
      foreach($this->getAcceptNestedAttributesFor() as $relation => $stack){
        $method_name = $relation;
        $presented_params = array_get($nested_attributes, $this->getNestedAttributesOptionOf($relation, 'param_name'));

        if(is_null($presented_params))
          $presented_params = [];

        if (empty(array_filter($presented_params)) && $this->getNestedAttributesOptionOf($relation, 'allow_blank'))
          continue;

        if (!method_exists($model, $method_name)) {
          if ($transaction_started){
            DB::rollback();
          }
          throw new \Exception('The nested atribute relation "' . $method_name . '" does not exists.');
        }

        $relation = $model->$method_name();

        if ($relation instanceof HasOne || $relation instanceof MorphOne) {
            $result = $this->saveNestedAttributes($model, $method_name, $presented_params);
            if ($result instanceOf MessageBag){
              $this->errors[snake_case($method_name).'_attributes'] = $result->messages();
            }
        } else if ($relation instanceof HasMany || $relation instanceof MorphMany) {
            foreach ($presented_params as $index => $params) {
              $result = $this->saveManyNestedAttributes($model, $method_name, $params);
              if ($result instanceOf MessageBag){
                if (!isset($this->errors[snake_case($method_name).'_attributes'])){
                  $this->errors[snake_case($method_name).'_attributes'] = array();
                }
                $this->errors[snake_case($method_name).'_attributes'][$index] = $result->messages();
              }
            }
        } else {
          if ($transaction_started){
            DB::rollback();
          }
          throw new Exception('The nested atribute relation is not supported for "' . $method_name . '".');
        }

      }
    }

    if (!$this->isErrorsPresent() && (!$root && $transaction_started)){
      DB::commit();
    }

    if ($this->isErrorsPresent() && (!$root && $transaction_started)){
      DB::rollback();
      throw new ValidatorException(new MessageBag($this->errors));
    }

    if ($this->isErrorsPresent()){
      return new MessageBag($this->errors);
    }

    $this->resetModel();
    event(new RepositoryEntityCreated($this, $model));

    return $this->parserResult($model);

  }

  public function update(array $attributes, $id, bool $transaction_started = false, $root = null){
    $this->applyScope();
    $this->applyCriteria();
    if (!$transaction_started && !$root){
      DB::beginTransaction();
      $transaction_started = true;
    }

    $this->resetErrors();

    #first we extract nested attributes params
    $nested_attributes = collect($attributes)->only($this->getAcceptedNestedAttributesParamKeys())->all();
    $attributes = collect($attributes)->except($this->getAcceptedNestedAttributesParamKeys())->all();

    #save record
    $mixed = array();

    $model = $this->find($id);

    if (!is_null($this->validator)) {

      // we should pass data that has been casts by the model
      // to make sure data type are same because validator may need to use
      // this data to compare with data that fetch from database.
      $instance = $model->newInstance();
      $casted_as_collection_attributes = collect($model->getCasts())->filter(function($value, $key){ return $value == 'collection'; })->keys();

      $previous_attributes = $model->toArray();

      foreach ($casted_as_collection_attributes->all() as $index => $key) {
        if(isset($previous_attributes[$key]) && ($previous_attributes[$key] instanceOf Collection ) ){
          $previous_attributes[$key] = $previous_attributes[$key]->all();
          if(isset($attributes[$key]) && is_array($attributes[$key])){
            $attributes[$key] = array_replace_recursive($previous_attributes[$key], $attributes[$key]);
          }
        }
      }

      $instance->forceFill(array_merge($previous_attributes, $attributes));

      if( $this->versionCompare($this->app->version(), "5.2.*", ">") ){
        $instance->makeVisible($this->model->getHidden());
      }else{
        $instance->addVisible($this->model->getHidden());
      }
      $mixed = $instance->toArray();
      #just insert file attributes for $attributes becase model#toArray not return it, fuckit
      if (isset($this->file_attributes) && is_array($this->file_attributes)){
        foreach ($this->file_attributes as $file_key) {
          if (isset($attributes[$file_key])){
            $mixed[$file_key] = $attributes[$file_key];
          }
        }
      }

      if (method_exists($this, 'rules')){
        $this->makeValidator($this->validator($mixed, $id, $root));
      }

      #we can not raise exception here as it will break nested attributes saving process
      try{
        $this->validator->with($mixed)->passesOrFail(ValidatorInterface::RULE_UPDATE);
      }catch(ValidatorException $e){
        $this->errors = $e->getMessageBag()->messages();
      }
    }

    //$attributes = array_merge($mixed, $attributes);

    $temporarySkipPresenter = $this->skipPresenter;

    $this->skipPresenter(true);
    $model = $this->model->findOrFail($id);

    //$attributes = array_merge($mixed, $attributes);

    $mixed = $this->beforeUpdate($mixed);
    $mixed = $this->beforeSave($mixed);

    //$mixed = array_filter($mixed, function($attribute){ return !is_null($attribute); });

    $model->fill($mixed);
    if (!$this->isErrorsPresent()){
      if ($model->save()){
        if (method_exists($this, 'afterSave')){
          $model = $this->afterSave($model, $mixed);
        }

        if (method_exists($this, 'afterUpdate')){
          $model = $this->afterSave($model, $mixed);
        }

      }
    }

    if (!empty($nested_attributes)){
      foreach($this->getAcceptNestedAttributesFor() as $relation => $stack){
        $method_name = $relation;
        $presented_params = array_get($nested_attributes, $this->getNestedAttributesOptionOf($relation, 'param_name'));

        if(is_null($presented_params))
          $presented_params = [];

        if (empty(array_filter($presented_params)) && $this->getNestedAttributesOptionOf($relation, 'allow_blank'))
          continue;

        if (!method_exists($model, $method_name)) {
          if ($transaction_started){
            DB::rollback();
          }
          throw new \Exception('The nested atribute relation "' . $method_name . '" does not exists.');
        }

        $relation = $model->$method_name();

        if ($relation instanceof HasOne || $relation instanceof MorphOne) {
            $result = $this->saveNestedAttributes($model, $method_name, $presented_params);
            if ($result instanceOf MessageBag){
              $this->errors[snake_case($method_name).'_attributes'] = $result->messages();
            }
        } else if ($relation instanceof HasMany || $relation instanceof MorphMany) {
            foreach ($presented_params as $index => $params) {

              $model->fill($attributes);
              $result = $this->saveManyNestedAttributes($model, $method_name, $params);
              if ($result instanceOf MessageBag){
                if (!isset($this->errors[snake_case($method_name).'_attributes'])){
                  $this->errors[snake_case($method_name).'_attributes'] = array();
                }
                $this->errors[snake_case($method_name).'_attributes'][$index] = $result->messages();
              }
            }
        } else {
          if ($transaction_started){
            DB::rollback();
          }
          throw new Exception('The nested atribute relation is not supported for "' . $method_name . '".');
        }

      }
    }

    if (!$this->isErrorsPresent() && (!$root && $transaction_started)){
      DB::commit();
    }

    if ($this->isErrorsPresent() && (!$root && $transaction_started)){
      DB::rollback();
      throw new ValidatorException(new MessageBag($this->errors));
    }

    if ($this->isErrorsPresent()){
      return new MessageBag($this->errors);
    }

    $this->resetModel();
    event(new RepositoryEntityUpdated($this, $model));

    return $this->parserResult($model);
  }

  protected function beforeSave(array $attributes = array()){
    return $attributes;
  }

  protected function beforeCreate(array $attributes = array()){
    return $attributes;
  }

  protected function beforeUpdate(array $attributes = array()){
    return $attributes;
  }

  public function newModelInstance(array $attributes = array(), $id = null){
    $nested_attributes = collect($attributes)->only($this->getAcceptedNestedAttributesParamKeys())->all();
    $attributes = collect($attributes)->except($this->getAcceptedNestedAttributesParamKeys())->all();
    $model = $this->model->newInstance()->forceFill($attributes);
    if($id){
       $model->{$model->getKeyName()} = $id;
      if($model->getKey()){
        $model->exists = true;
      }
    }
    foreach($this->getAcceptNestedAttributesFor() as $relation => $stack){
      $relation_name = $relation;
      $presented_params = array_get($nested_attributes, $this->getNestedAttributesOptionOf($relation, 'param_name'), []);
      $repository = $this->app->make($this->getNestedAttributesOptionOf($relation_name, 'repository_name'));
      //$model->$relation_name = $repository->newModelInstance($presented_params);

      if ($model->$relation_name() instanceof HasOne || $model->$relation_name() instanceof MorphOne) {

        $relation_class = get_class($model->$relation_name()->getRelated());

        if (!$model->$relation_name instanceOf $relation_class) {
          $model->$relation_name = $repository->newModelInstance($presented_params);
        }

      }else if ($model->$relation_name() instanceof HasMany || $model->$relation_name() instanceof MorphMany) {
        if (!$model->$relation_name instanceOf EloquentCollection){
          $model->$relation_name = collect();
        }
        foreach ($presented_params as $index => $params) {
          if(isset($model->$relation_name[$index])){
            $model->$relation_name[$index]->forceFill($params);
          }else{
            $model->$relation_name->push($repository->newModelInstance($params));
          }
        }
      }
    }
    return $model;
  }

  public function findWhereFirst(array $where, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyConditions($where);
        $model = $this->model->get($columns)->first();
        $this->resetModel();
        return $model ? $this->parserResult($model) : null;
    }

}
