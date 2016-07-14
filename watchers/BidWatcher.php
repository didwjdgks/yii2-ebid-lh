<?php
namespace ebidlh\watchers;

use ebidlh\WatchEvent;

class BidWatcher extends \yii\base\Component
{
  const URL='http://ebid.lh.or.kr/ebid.et.tp.cmd.BidMasterListCmd.dev';

  private $pub;
  private $sub;
  private $post;
  private $module;
  private $channel;

  public function init(){
    parent::init();
    $this->module=\ebidlh\Module::getInstance();
    $this->pub=\Yii::createObject([
      'class'=>\ebidlh\Redis::className(),
      'hostname'=>$this->module->redis_server,
    ]);
    $this->sub=\Yii::createObject([
      'class'=>\ebidlh\Redis::className(),
      'hostname'=>$this->module->redis_server,
    ]);
  }

  public function watch(){
    $this->channel=\Yii::$app->security->generateRandomString();
    $this->pub->publish('ebidlh-bid',[
      'cmd'=>'open-watch',
      'channel'=>$this->channel,
    ]);
    try {
      $this->sub->subscribe([$this->channel],[$this,'onSubscribe']);
    }
    catch(\RedisException $e){
      $this->sub->close();
      throw $e;
    }
  }

  public function onSubscribe($redis,$chan,$msg){
    if($msg==='ready'){
      $this->post=[
        's_tndrdocAcptOpenDtm'=>date('Y/m/d',strtotime('-1 day')),
        's_tndrdocAcptEndDtm'=>date('Y/m/d',strtotime('+3 month')),
        'pageSpec'=>'default',
        'targetRow'=>1,
      ];
      $this->pub->publish($this->channel.'-client',[
        'url'=>self::URL,
        'post'=>http_build_query($this->post),
      ]);
      return;
    }

    $html=iconv('euckr','utf-8//IGNORE',$msg);
    $html=strip_tags($html,'<tr><td>');
    $html=preg_replace('/<td[^>]*>/i','<td>',$html);

    if(strpos($html,'한국정보인증(주)의 보안S/W를 설치중입니다')>0) return;

    $p='#<tr onclick="fn_dds_open\(\'\d{7}\', \'(?<subno>\d{2})\', \'(?<param3>\d{2})\', \'(?<param4>.)\'\)[^>]*>'.
        ' <td>(?<notinum>\d{7})</td>'.
        ' <td>(?<bidtype>[^<]*)</td>'.
        ' <td>(?<bidproc>[^<]*)</td>'.
        ' <td>(?<constnm>[^<]*)</td>'.
        ' <td>(?<contract>[^<]*)</td>'.
        ' <td>(?<closedt>[^<]*)</td>'.
        ' <td>(?<local>[^<]*)</td>'.
        ' <td>(?<status>[^<]*)</td>'.
       ' </tr>#i';
    $p=str_replace(' ','\s*',$p);
    if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        $row=[
          'notinum'=>trim($m['notinum']),
          'subno'=>trim($m['subno']),
          'bidtype'=>trim($m['bidtype']),
          'bidproc'=>trim($m['bidproc']),
          'constnm'=>trim($m['constnm']),
          'contract'=>trim($m['contract']),
          'closedt'=>trim($m['closedt']),
          'local'=>trim($m['local']),
          'status'=>trim($m['status']),
          'param3'=>trim($m['param3']),
          'param4'=>trim($m['param4']),
        ];
        $event=new WatchEvent;
        $event->row=$row;
        $this->trigger(WatchEvent::EVENT_ROW,$event);
      }
    }

    if(preg_match('#/ (?<total_page>\d+) 페이지\)#',$html,$m)){
      $total_page=intval($m['total_page']);
    }
    if(!$total_page){
      $this->sub->close();
      throw new \Exception('total_page is null');
    }
    $page=ceil($this->post['targetRow']/10);
    echo "page: $page/$total_page\n";
    if($page==$total_page){
      $this->pub->publish($this->channel.'-client',[
        'url'=>'close',
        'post'=>'',
      ]);
      $this->sub->close();
      return;
    }else{
      $this->post['targetRow']+=10;
    }
    sleep(mt_rand($this->module->delay_min,$this->module->delay_max));
    $this->pub->publish($this->channel.'-client',[
      'url'=>self::URL,
      'post'=>http_build_query($this->post),
    ]);
  }
}

