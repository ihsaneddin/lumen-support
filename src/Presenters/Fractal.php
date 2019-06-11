<?php
namespace Support\Presenters;

use Prettus\Repository\Presenter\FractalPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Fractal extends FractalPresenter {

  abstract public function getTransformer();

}
