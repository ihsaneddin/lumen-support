<?php
namespace Support\Entities\Traits;

use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;

use Support\Entities\Observers\UuidObserver;

trait UuidTrait
{

    protected $__uuid_enabled = true;
    protected $__uuid_column = "uuid";

    public function getUuidEnabledAttribute(){
        return $this->__uuid_enabled;
    }

    public function getUuidColumnAttribute(){
        return $this->__uuid_column;
    } 

    public static function bootUuidTrait(){
      static::observe(app(UuidObserver::class));
    }

    /**
     * Scope  by uuid
     * @param  string  uuid of the model.
     *
    */
    public function scopeUuid($query, $uuid, $first = true)
    {
        $match = preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $uuid);

        if (!is_string($uuid) || $match !== 1)
        {
            throw (new ModelNotFoundException)->setModel(get_class($this));
        }

        $results = $query->where(config('uuid.default_uuid_column'), $uuid);

        return $first ? $results->firstOrFail() : $results;
    }

}
