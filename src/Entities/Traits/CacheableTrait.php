<?php
namespace Support\Entities\Traits;

use Support\Entities\Observers\CacheableObserver;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Cache;
use Illuminate\Support\Facades\Redis;

trait CacheableTrait {

  protected static function _cache_prefix(){
    return env("APP_NAME").":cacheable:";
  }

  protected function object_cache_keys(){
    $identifier = isset(static::$_cache_identifier) ? static::$_cache_identifier : 'id';
    if (method_exists($this, "getCacheKeys")){
      return array_merge([$this->{$identifier}], $this->getCacheKeys());
    }
    return [$this->{$identifier}];
  }

  protected static function cache_keys(){
    if (method_exists(static::class, 'getClassCacheKeys')){
      return array_merge(['collection'], static::getClassCacheKeys());
    }
    return ['collection'];
  }

  public function cache_key($key=null, $id=null){
    $id= is_null($id) ? $this->id : $id;
    $name = self::_cache_prefix(). "-cached-" .get_class($this). "-" .$id. "-" .$key;

    return $name;
  }

  public static function _cache_key($key){
    return $name= static::_cache_prefix(). "-cached-" .get_called_class(). "-" .$key;
  }

  public function clear_cache(){
    foreach ($this->object_cache_keys() as $key) {
      Cache::forget($this->cache_key($key));
    }
  }

  public static function _clear_cache(){
    $keys = static::cache_keys();
    foreach ( $keys as $key) {
      $key = static::_cache_key($key);
      Cache::forget( $key );
    }
  }

  protected static function default_expired_at(){
    $minutes = isset(static::$_default_expired_at) ? static::$_default_expired_at : 3600;
    return Carbon::now()->addMinutes($minutes);
  }

  public static function bootCacheableTrait()
  {
    static::observe(app(CacheableObserver::class));
  }

  protected static function _cache_get_model_events()
  {
      if (isset(static::$clear_cached_when)) {
          return static::$clear_cached_when;
      }

      return [
        'saved',
        'deleted',
      ];
  }

  public static function cache_find($key){

    /*$model = Cache::get(static::_cache_key($key));\
    if (is_null($model)){
      $identifier = isset(static::$_cache_identifier) ? static::$_cache_identifier : $this->getKeyName();
      $model = self::where($identifier, '=', $key)->first();
      if (is_null($model)){
        return $model;
      }
    }

    return Cache::remember(static::_cache_key($key), self::default_expired_at(), function() use($model) {
      return $model;
    });
    */
    $model =  Cache::get(static::_cache_key($key));

    if (!is_null($model)) return $model;

    $identifier = isset(static::$_cache_identifier) ? static::$_cache_identifier : (new static)->getKeyName();

    $model = self::where($identifier, '=', $key)->first();

    if (is_null($model)) return $model;

    return Cache::remember( $model->cache_key($key), self::default_expired_at(), function() use($model) {
      return $model;
    });
  }

  public static function cached_collection(array $condition= array()){
    return Cache::remember(self::_cache_key('collection'), self::default_expired_at(), function() use($condition){
      return self::where($condition)->get();
    });
  }

  public static function cacheThis(string $key, $data){
    return Cache::remember(static::_cache_key($key), static::default_expired_at(), function() use($data){
      return $data;
    });
  }

  public static function getCachedDataOf(string $key){
    return Cache::get(static::_cache_key($key));
  }

  #
  # new ways to clear the cache by object or collection keys
  #

  //protected $cacheable_object_keys = [];
  //protected $cacheable_collection_keys = [];

  public function clearCachedObjectKeys(array $keys = []){
      if (property_exists($this,'cacheable_object_keys')){
        $options = $this->cacheable_collection_keys;
        if(is_array($options)){
          $keys = array_merge($keys, $options);
        }
      }
    foreach ($keys as $key) {
      Cache::forget($this->cache_key($key));
    }
  }

  public function clearCachedCollectionKeys(array $keys = []){
     if (property_exists($this,'cacheable_collection_keys')){
        $options = $this->cacheable_collection_keys;
        if(is_array($options)){
          $keys = array_merge($keys, $options);
        }
     }

    foreach ( $keys as $key) {
      $key = static::_cache_key($key);
      Cache::forget( $key );
    }
    if(isset(static::$singleTableSubclasses) && is_array(static::$singleTableSubclasses)){
      foreach(static::$singleTableSubclasses as $sub_class){
        $model = new $sub_class;
        if(method_exists($model, "getCachedCollectionKeys")){
            if(!is_array($model->getCachedCollectionKeys())){
                $getCachedCollectionKey = [];
            }else{ $getCachedCollectionKey = $model->getCachedCollectionKeys();}
          foreach ($getCachedCollectionKey as $key) {
            Cache::forget($sub_class::_cache_key($key));
          }
        }
      }
    }
    if(isset(static::$singleTableType)){
      $parent_class = get_parent_class($this);
      $model = new $parent_class;
      if(method_exists($model, "getCachedCollectionKeys")){
        foreach ($model->getCachedCollectionKeys() as $key) {
          Cache::forget($parent_class::_cache_key($key));
        }
      }
    }
  }

  public function pushKeyToCachedObjectKeys(array $keys = []){
    if (!is_array($keys)){
      $keys = array($keys);
    }
    $this->cacheable_object_keys = array_merge($this->cacheable_object_keys, $keys);
    return $this->cacheable_object_keys;
    //return array_push($this->cacheable_object_keys, $key);
  }

  public function getCachedObjectKeys(){
    if(property_exists($this, 'property_exists')){
      $this->cacheable_object_keys ?: [];
    }
    return [];
  }

  public function pushKeyToCachedCollectionKeys(array $keys = []){
    if (!is_array($keys)){
      $keys = array($keys);
    }
    $this->cacheable_collection_keys = array_merge($this->cacheable_collection_keys, $keys);
    return $this->cacheable_collection_keys;
    //return array_push($this->cacheable_collection_keys, $key);
  }

  public function getCachedCollectionKeys(){
    if(property_exists($this, 'property_exists')){
      $this->cacheable_collection_keys ?: [];
    }
    return [];
  }

  public function store_attribute_on_cache_forever(string $attribute, string $key = null){
    if(!$key){
      $key = static::_cache_prefix().$this->$attribute;
    }
    try{
      Cache::forever($key,$this->$attribute);
    }catch( \Exception $e ){}
  }

  public static function fetch(string $key){
    return Cache::get(static::_cache_prefix().$key);
  }

  public static function is_stored_in_cache(string $key){
    return Cache::get(static::_cache_prefix().$key) != null;
  }

  public function storeWithinRedisCacheCluster(string $value){
     Redis::connection($this->redis_connection_cache)->set($this->$value, Carbon::now());
  }

}
