<?php
namespace Support\Repositories\Scopes;

use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class SortAndOrderByScope implements CriteriaInterface
{

  public function apply($model, RepositoryInterface $repository)
  {
    $sort = request()->get("sort", "asc");
    $order = request()->get("order", "created_at");
    return $model->orderBy($sort, $order);
  }

}