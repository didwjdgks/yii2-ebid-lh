<?php
namespace ebidlh\controllers;

use ebidlh\workers\SucWorker;
use ebidlh\workers\BidWorker;

class WorkController extends \yii\console\Controller
{
  public function actionBid(){
    $worker=new \GearmanWorker;
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('ebidlh_bid_work',[BidWorker::className(),'work']);
    while($worker->work());
  }

  public function actionSuc(){
    $worker=new \GearmanWorker;
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('ebidlh_suc_work',[SucWorker::className(),'work']);
    while($worker->work());
  }
}

