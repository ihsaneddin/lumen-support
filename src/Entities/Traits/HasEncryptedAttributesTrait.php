<?php 
namespace Support\Entities\Traits;
//  Standard encryption and decryption for eloquent model attributes

use Illuminate\Support\Facades\Crypt;

trait HasEncryptedAttributesTrait {

  public function getAttribute($key)
  {
    $value = parent::getAttribute($key);
    if($value){
      if (in_array($key, $this->getEncryptableColumnsAttribute())) {
        $value = decrypt($value);
        return $value;
      }
    }
    return $value;
  }

  public function setAttribute($key, $value)
  {
    if($value){
      if (in_array($key, $this->getEncryptableColumnsAttribute())) {
        $value = encrypt($value);
      }
    }
    return parent::setAttribute($key, $value);
  }

  public function getEncryptableColumnsAttribute(){
    $encryptable = [];
    if(property_exists($this, "encryptable")){
      $encryptable = $this->encryptable;
    }
    if(empty($encryptable)){
      if(method_exists($this, "encryptable")){
        $encryptable = $this->encryptable();
      }
    }
    return is_array($encryptable) ? $encryptable : [];
  }

  public function attributesToArray()
  {
      $attributes = parent::attributesToArray();
      $encryptable_fields= $this->getEncryptableColumnsAttribute();
      foreach ($encryptable_fields as $key) {
          if (isset($attributes[$key])) {
              $attributes[$key] = decrypt($attributes[$key]);
          }
      }
      return $attributes;
  }

}