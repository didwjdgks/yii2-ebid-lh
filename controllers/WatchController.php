<?php
namespace ebidlh\controllers;

use Yii;
use yii\helpers\Json;
use yii\helpers\Console;
use yii\helpers\ArrayHelper;

use ebidlh\WatchEvent;
use ebidlh\watchers\BidWatcher;
use ebidlh\watchers\SucWatcher;

use ebidlh\models\BidKey;

class WatchController extends \yii\console\Controller
{
  public $gman_client;

  public function init(){
    parent::init();
    $this->gman_client=new \GearmanClient;
    $this->gman_client->addServers($this->module->gman_server);
  }

  public function actionBid(){
    $watcher=new BidWatcher;
    $watcher->on(WatchEvent::EVENT_ROW,[$this,'onBidRow']);

    while(true){
      try{
        $watcher->watch();
      }
      catch(\Exception $e){
        $this->stdout($e.PHP_EOL,Console::FG_RED);
        Yii::error($e,'ebidlh');
      }
      
      $this->stdout(sprintf("[%s] Peak memory usage: %sMb\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024),
        Console::FG_GREY
      ));
      sleep(mt_rand($this->module->delay_min,$this->module->delay_max));
    }
  }

  public function actionSuc(){
    $watcher=new SucWatcher;
    $watcher->on(WatchEvent::EVENT_ROW,[$this,'onSucRow']);
    
    while(true){
      try{
        $watcher->watch();
      }
      catch(\Exception $e){
        $this->stdout($e.PHP_EOL,Console::FG_RED);
        Yii::error($e,'ebidlh');
      }

      $this->stdout(sprintf("[%s] Peak memory usage: %sMb\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024),
        Console::FG_GREY
      ));
      sleep(mt_rand($this->module->delay_min,$this->module->delay_max));
    }
  }

  public function onBidRow($event){
    $row=$event->row;
    $this->stdout(join(',',$row)."\n");
  }

  public function onSucRow($event){
    $row=$event->row;

    if(ArrayHelper::isIn($row['status'],['공개','유찰'])){
      $bidkey=BidKey::findOne([
        'whereis'=>'05',
        'notinum'=>$row['notinum'].'-'.$row['subno'],
      ]);
      if($bidkey!==null){
        if($row['status']==='유찰' && $bidkey->bidproc==='F') return;
        if($row['status']==='공개' && $bidkey->bidproc==='S') return;
        $this->gman_client->doBackground('ebidlh_suc_work',Json::encode($row));
      }
    }
  }
}

