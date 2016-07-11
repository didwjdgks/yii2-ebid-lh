<?php
namespace ebidlh\controllers;

use ebidlh\workers\SucWorker;

class WorkController extends \yii\console\Controller
{
  public function actionBid(){
  }

  public function actionSuc(){
    $worker=new \GearmanWorker;
    $worker->addServers($this->gman_server);
    $worker->addFunction('ebidlh_suc_work',[SucWorker::className(),'work']);
    while($worker->work());
  }
}

