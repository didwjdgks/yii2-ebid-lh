<?php
namespace ebidlh;

class Redis extends \yii\base\Component
{
  public $hostname='localhost';
  public $port=6379;

  public $phpRedis;

  public function open(){
    if($this->phpRedis!==null) return;
    $this->phpRedis=new \Redis();
    $this->phpRedis->connect($this->hostname,$this->port);
    $this->phpRedis->setOption(\Redis::OPT_READ_TIMEOUT,30);
  }

  public function close(){
    if($this->phpRedis!==null){
      $this->phpRedis->close();
      $this->phpRedis=null;
    }
  }

  public function publish($channel,$message){
    if(empty($message)) return;

    if(is_array($message)){
      $message=\yii\helpers\Json::encode($message,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES);
    }
    $this->open();
    return $this->phpRedis->publish($channel,$message);
  }

  public function subscribe(array $channel,$callback){
    $this->open();
    $this->phpRedis->subscribe($channel,$callback);
  }
}

