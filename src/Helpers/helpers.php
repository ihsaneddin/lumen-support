<?php
/**
 * Created by PhpStorm.
 * User: billy
 * Date: 08/04/19
 * Time: 14:00
 */

use Illuminate\Http\Request;

if (! function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param  array|string  $key
     * @param  mixed   $default
     * @return \Illuminate\Http\Request|string|array
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return app(Request::class);
        }
        if (is_array($key)) {
            return app(Request::class)->only($key);
        }
        $value = app(Request::class)->__get($key);
        return is_null($value) ? value($default) : $value;
    }
}

if ( ! function_exists('slack_message'))
{
  function slack_message($message){
    dispatch(new \Jobs\SlackMessageJob($message));
  }

}
