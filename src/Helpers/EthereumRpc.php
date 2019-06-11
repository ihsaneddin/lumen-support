<?php
/**
 * Created by PhpStorm.
 * User: billy
 * Date: 15/04/19
 * Time: 9:42
 */

namespace Support\Helpers;


use Ihsaneddin\Ethereum\Traits\EthereumTrait;

class EthereumRpc
{

    use EthereumTrait;

    public function __call($name, $arguments)
    {
      return $this->ethereum()->rpc(function($rpc)  use($name, $arguments) {
        if(is_array($arguments))
          $arguments = implode(',', $arguments);
        return $rpc->$name($arguments);
      })->result;
    }
}
