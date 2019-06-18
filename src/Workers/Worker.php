<?php

namespace Support\Workers;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class Worker implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use InteractsWithQueue, Queueable, SerializesModels;

    protected function pessimisticLock(Model $model, callable $callback){
        try{
            DB::beginTransaction();
            $model = $model->where($model->getKeyName(), $model->getKey())->lockForUpdate()->first();
            if($model){
                $callback($model);
            }
            DB::commit();
        }catch(\Throwable $exception){
            DB::rollBack();
            throw $exception;
        }
    }
}
