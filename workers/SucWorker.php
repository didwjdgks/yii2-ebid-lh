<?php
namespace ebidlh\workers;

use yii\helpers\Console;

class SucWorker
{
  public static function work($job){
    $workload=$job->workload();
    echo $workload,PHP_EOL;

    try{
    }
    catch(\Exception $e){
      echo Console::renderColoredString("%r$e%n"),PHP_EOL;
      \Yii::error($job->workload()."\n".$e,'ebidlh');
    }

    echo Console::renderColoredString("%c".
          sprintf("[%s] Peak memory usage: %sMb",
            date('Y-m-d H:i:s'),
            (memory_get_peak_usage(true)/1024/1024)
          )."%n"
    ),PHP_EOL;
  }
}

