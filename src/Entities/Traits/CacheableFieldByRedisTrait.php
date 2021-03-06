<?php
namespace Support\Entities\Traits;

use Illuminate\Support\Facades\Redis;

trait CacheableFieldByRedisTrait {

  public function storeOnCache(string $key, $value){
    if ($this->redis_connection_name){
      return Redis::connection($this->redis_connection_name)->set($key, $value);
    }else{
      return Redis::set($key, $value);
    }
  }

  public function fetchFromCache(string $key){
    if ($this->redis_connection_name){
      return Redis::connection($this->redis_connection_name)->get($key);
    }else{
      return Redis::get($key);
    }
  }

  public function removeFromCache(string $key){
    if ($this->redis_connection_name){
      return Redis::connection($this->redis_connection_name)->del($key);
    }else{
      return Redis::del($key);
    }
  }

  public function getRedisConnectionNameAttribute(){
    if(property_exists($this, "redis_connection_name")){
      return $this->redis_connection_name;
    }
    if(method_exists($this, "getRedisConnectionName")){
      return $this->getRedisConnectionName();
    }
  }

  public static function isKeyExistsonRedis(string $key){
    return (new static)->fetchFromCache($key);
  }

}
