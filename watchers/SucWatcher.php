<?php
namespace ebidlh\watchers;

use ebidlh\WatchEvent;

class SucWatcher extends \yii\base\Component
{
  const URL='http://ebid.lh.or.kr/ebid.et.tp.cmd.TenderOpenListCmd.dev';

  public $pub;
  public $sub;

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
    $this->pub->publish('ebidlh-suc',[
      'cmd'=>'open-watch',
      'channel'=>$this->channel,
    ]);
    try {
      $this->sub->subscribe([$this->channel],[$this,'onSubscribe']);
    }catch(\RedisException $e){
      $this->sub->close();
      throw $e;
    }
  }

  public function onSubscribe($redis,$chan,$msg){
    if($msg==='ready'){
      $this->post=[
        's_bidnmKor'=>'',
        's_openDtm1'=>date('Y/m/d',strtotime('-1 month')),
        's_openDtm2'=>date('Y/m/d'),
        's_cstrtnJobGbCd'=>'',
        's_bidNum'=>'',
        'pageSpec'=>'default',
        'targetRow'=>1,
        'devonOrderBy'=>'',
        'selectednum'=>'',
      ];
      $this->pub->publish($this->channel.'-client',[
        'url'=>self::URL,
        'post'=>http_build_query($this->post),
      ]);
      return;
    }
    $html=iconv('euckr','utf-8//IGNORE',$msg);
    $html=strip_tags($html,'<tr><td><option>');
    $html=preg_replace('/<td[^>]*>/i','<td>',$html);

    if(strpos($html,'한국정보인증(주)의 보안S/W를 설치중입니다')>0){
      return;
    }

    $p='#<tr onclick="fn_dds_open\(\'\d{7}\', \'(?<subno>\d{2})\',[^>]*>'.
        ' <td>(?<notinum>\d{7})</td>'.
        ' <td>(?<bidtype>[^<]*)</td>'.
        ' <td>(?<bidcls>[^<]*)</td>'.
        ' <td>(?<constnm>[^<]*)</td>'.
        ' <td>(?<constdt>\d{4}/\d{2}/\d{2} \d{2}:\d{2})</td>'.
        ' <td>(?<status>[^<]*)</td>'.
       ' </tr>#i';
    if(preg_match_all(str_replace(' ','\s*',$p),$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        $row=[
          'notinum'=>trim($m['notinum']),
          'subno'=>trim($m['subno']),
          'bidtype'=>trim($m['bidtype']),
          'bidcls'=>trim($m['bidcls']),
          'constnm'=>trim($m['constnm']),
          'constdt'=>trim($m['constdt']),
          'status'=>trim($m['status']),
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

