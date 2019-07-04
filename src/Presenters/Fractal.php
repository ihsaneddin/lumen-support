<?php
namespace Support\Presenters;

use Prettus\Repository\Presenter\FractalPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\AbstractPaginator;
use League\Fractal\Manager;
use League\Fractal\Resource\NullResource;

abstract class Fractal extends FractalPresenter {

  abstract public function getTransformer();

  public function present($data)
  {
    if (!class_exists('League\Fractal\Manager')) {
      throw new Exception(trans('repository::packages.league_fractal_required'));
    }

    if ($data instanceof EloquentCollection) {
      $this->resource = $this->transformCollection($data);
    } elseif ($data instanceof AbstractPaginator) {
      $this->resource = $this->transformPaginator($data);
    } else {
      if(is_null($data)){
        $this->resource = $this->transformNull($data);
      }else{
        $this->resource = $this->transformItem($data);
      }
    }
    return $this->fractal->createData($this->resource)->toArray();
  }


  protected function transformNull($data){
    return new NullResource($data, $this->getTransformer());
  }

}
