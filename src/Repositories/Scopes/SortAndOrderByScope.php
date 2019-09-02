<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class SortAndOrderByScope implements CriteriaInterface
{

  public function apply($model, RepositoryInterface $repository)
  {
    $sort = request()->get("sort", "created_at");
    $order = request()->get("order", "asc");
    return $model->orderBy($sort, $order);
  }

}