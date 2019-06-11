<?php
namespace Support\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    protected $repository_name;
    protected $repository;
    protected $repository_scopes = array();
    protected $repository_presenter = null;
    protected $middlewares = [];

    public function __construct(){
      $this->appendMiddlewares();  
      $this->initializeRepository();   
    }

    protected function initializeRepository() : void {
      if ($this->repository_name){
          $repository_class = array_get(config('repositories', array()), $this->repository_name);
          if(is_null($repository_class) && class_exists($this->repository_name)){
              $repository_class = $this->repository_name;
          }
          if ($repository_class){
              $this->repository = app($repository_class);
              foreach ($this->repository_scopes as $index => $scope) {
                  if (is_numeric($index))
                      $this->repository->pushCriteria($scope);
                  else
                      $this->repository->pushCriteria(new $index($scope));
              }

              if ($this->repository_presenter)
              $this->repository->setPresenter($this->repository_presenter);

          }
      }
    }

    protected function appendMiddlewares(){
        foreach($this->middlewares as $index => $middleware){
            if(is_numeric($index)){
                $this->middleware($middleware);
            }
            if(is_array($middleware)){
                $only = array_get($middleware, "only");
                $except = array_get($middleware, 'except');
                $this->middleware($index)->only($only)->except($except);
            }
        }
    }
    
}
