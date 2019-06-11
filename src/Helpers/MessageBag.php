<?php
namespace Support\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class MessageBag extends \Illuminate\Support\MessageBag{

  /**
   * Get all of the messages from the bag for a given key.
   *
   * @param  string  $key
   * @param  string  $format
   * @return array
   */
  public function get($key, $format = null)
    {
        // If the message exists in the container, we will transform it and return
        // the message. Otherwise, we'll check if the key is implicit & collect
        // all the messages that match a given key and output it as an array.
        if (array_key_exists($key, $this->messages)) {
            return $this->transform(
                $this->messages[$key], $this->checkFormat($format), $key
            );
        }
        if (array_has($this->messages, $key)) {
            return $this->transform(
                array_get($this->messages, $key), $this->checkFormat($format), $key
            );
        }

        return $this->transform(
                //$this->messages[$key], $this->checkFormat($format), $key
                array_get($this->messages, $key), $this->checkFormat($format), $key
            );

        if (Str::contains($key, '*')) {
            return $this->getMessagesForWildcardKey($key, $format);
        }

        return [];
    }

  /**
   * Determine if messages exist for all of the given keys.
   *
   * @param  array|string  $key
   * @return bool
   */
  public function has($key)
  {
      if (is_null($key)) {
          return $this->any();
      }
      $keys = is_array($key) ? $key : func_get_args();

      foreach ($keys as $key) {
          if ($this->first($key) === '') {
              return false;
          }
      }

      return true;
  }

  /**
   * Get the first message from the bag for a given key.
   *
   * @param  string  $key
   * @param  string  $format
   * @return string
   */
  public function first($key = null, $format = null)
  {
      $messages = is_null($key) ? $this->all($format) : $this->get($key, $format);

      $firstMessage = Arr::first($messages, null, '');

      return is_array($firstMessage) ? Arr::first($firstMessage) : $firstMessage;
  }

}