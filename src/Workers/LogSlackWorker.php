<?php
namespace Support\Workers;

use Illuminate\Support\Facades\Log;

class LogSlackWorker extends Worker
{
  /**
   * Create a new job instance.
   *
   * @return void
   */
  protected $name;
  protected $argument;
  public function __construct($name, $arguments)
  {
    $this->name = $name;
    $this->argument = $arguments;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $name = $this->name;
     Log::channel('slack')->$name($this->argument);
  }
}
