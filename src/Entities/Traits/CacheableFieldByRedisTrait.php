<?php
namespace Support\Entities\Traits;

use Illuminate\Support\Facades\Redis;

trait CacheableFieldByRedisTrait {

  public function storeOnCache(string $key, $value){
    if ($this->getRedisConnectionName()){
      return Redis::connection($this->getRedisConnectionName())->set($key, $value);
    }else{
      return Redis::set($key, $value);
    }
  }

  public function fetchFromCache(string $key){
    if ($this->getRedisConnectionName()){
      return Redis::connection($this->getRedisConnectionName())->get($key);
    }else{
      return Redis::get($key);
    }
  }

  public function removeFromCache(string $key){
    if ($this->getRedisConnectionName()){
      return Redis::connection($this->getRedisConnectionName())->del($key);
    }else{
      return Redis::del($key);
    }
  }

  public function getRedisConnectionNameAttribute(){
    if(property_exists($this, "getRedisConnectionName()")){
      return $this->getRedisConnectionName();
    }
    if(method_exists($this, "getRedisConnectionName")){
      return $this->getRedisConnectionName();
    }
  }

  public static function isKeyExistsonRedis(string $key){
    return (new static)->fetchFromCache($key);
  }

  public function getRedisConnectionName(){
    return $this->getRedisConnectionName();
  }

}
