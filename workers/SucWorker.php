<?php
namespace ebidlh\workers;

use yii\helpers\Json;
use yii\helpers\Console;
use yii\helpers\ArrayHelper;

use ebidlh\PassException;
use ebidlh\models\BidKey;

class SucWorker extends \yii\base\Object
{
  const URL='http://ebid.lh.or.kr/ebid.et.tp.cmd.TenderOpenDetailCmd.dev';

  public static function work($job){
    $workload=$job->workload();
    $workload=Json::decode($workload);

    $module=\ebidlh\Module::getInstance();
    $pub=\Yii::createObject([
      'class'=>\ebidlh\Redis::className(),
      'hostname'=>$module->redis_server,
    ]);
    $sub=\Yii::createObject([
      'class'=>\ebidlh\Redis::className(),
      'hostname'=>$module->redis_server,
    ]);
    $channel=\Yii::$app->security->generateRandomString();

    try{
      $bidkey=BidKey::findOne([
        'whereis'=>'05',
        'notinum'=>$workload['notinum'].'-'.$workload['subno'],
      ]);
      if($bidkey===null){
        throw new \Exception('개찰: 입찰공고 미등록 공고입니다. ('.$workload['notinum'].')');
      }

      static::print_bid($bidkey);

      if(ArrayHelper::keyExists('status',$workload) && $workload['status']==='유찰'){
        if($bidkey->bidproc==='F' and !ArrayHelper::keyExists('force',$workload)){
          throw new PassException;
        }
        //todo
        throw new PassException;
      }

      if($bidkey->bidproc==='S' and !ArrayHelper::keyExists('force',$workload)){
        throw new PassException;
      }

      $pub->publish('ebidlh-suc',[
        'cmd'=>'open-work',
        'channel'=>$channel,
      ]);
      $sub->subscribe([$channel],function ($redis,$chan,$msg) use ($pub,$channel,$workload,$bidkey) {
        if($msg==='ready'){
          $pub->publish($channel.'-client',[
            'url'=>self::URL,
            'post'=>http_build_query(['bidNum'=>$workload['notinum'],'bidDegree'=>$workload['subno']]),
          ]);
          return;
        }
        $html=iconv('euckr','utf-8//IGNORE',$msg);
        $html=strip_tags($html,'<th><tr><td>');
        $html=preg_replace('/<th[^>]*>/i','<th>',$html);
        if(strpos($html,'한국정보인증(주)의 보안S/W를 설치중입니다')>0) return;

        $p='@<td[^>]+>(?<no>\d{1,2})</td>'.
           ' <td(?<selms>[^>]+)>(?<mprice>\d+(,\d{1,3})*) </td>'.
           ' <td[^>]+>(?<mcnt>\d*)</td>@i';
        $p=str_replace(' ','\s*',$p);
        $multispares=[];
        $selms=[];
        $mulcnts=[];
        if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
          foreach($matches as $m){
            $multispares[$m['no']-1]=str_replace(',','',$m['mprice']);
            if(strpos($m['selms'],'COLOR: #ff0000;')>0)
              $selms[]=$m['no'];
            $mulcnts[$m['no']-1]=$m['mcnt'];
          }
        }
        ksort($multispares);
        ksort($mulcnts);
        sort($selms);

        $html=preg_replace('/<td[^>]*>/i','<td>',$html);
        $yega='';
        $p='#<th> 예정가격 </th> <td>(?<yega>\d+(,\d{3})*) 원</td>#i';
        $p=str_replace(' ','\s*',$p);
        if(preg_match($p,$html,$m)){
          $yega=str_replace(',','',$m['yega']);
        }

        //참여업체 (금액으로 sort됨)
        $succoms=[];
        $succoms_plus=[];
        $succoms_minus=[];
        $p='#<tr>'.
            ' <td>(?<seq>\d+)</td>'.
            ' <td>(?<officeno>[^>]*)</td>'.
            ' <td>(?<officenm>[^>]*)</td>'.
            ' <td>(?<success>\d+(,\d{1,3})*)</td>'.
            ' <td>(?<pct>[^<]*)</td>'.
            ' <td>(?<selms>[^<]*)</td>'.
            ' <td>(?<etc>[^<]*)</td>'.
           ' </tr>#i';
        $p=str_replace(' ','\s*',$p);
        if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
          foreach($matches as $m){
            $succom=[
              'seq'=>$m['seq'],
              'officeno'=>str_replace('-','',trim($m['officeno'])),
              'officenm'=>trim($m['officenm']),
              'success'=>str_replace(',','',trim($m['success'])),
              'pct'=>trim($m['pct']),
              'selms'=>trim($m['selms']),
              'etc'=>trim($m['etc']),
            ];
            $succoms[$succom['seq']]=$succom;

            switch($succom['etc']){
              case '점수미달':
              case '낙찰하한율미만':
              case '부적격':
                $succoms_minus[]=$succom['seq'];
                break;
              default:
                $succoms_plus[]=$succom['seq'];
            }
          }
        }

        $i=1;
        foreach($succoms_plus as $seq){
          $succoms[$seq]['rank']=$i;
          $i++;
        }
        $i=count($succoms_minus)*-1;
        foreach($succoms_minus as $seq){
          $succoms[$seq]['rank']=$i;
          $i++;
        }

        $innum=count($succoms);
        Console::startProgress(0,$innum);
        $n=1;
        foreach($succoms as $com){
          Console::updateProgress($n,$innum);
          $n++;
        }
        Console::endProgress();

        $pub->publish($channel.'-client',[
          'url'=>'close','post'=>'',
        ]);
        $redis->close();
        return;
      });
    }
    catch(PassException $e){
    }
    catch(\RedisException $e){
      $sub->close();
      echo Console::renderColoredString('%rRedisException: '.$e->getMessage().'%n'),PHP_EOL;
      $gman_client=new \GearmanClient;
      $gman_client->addServers($module->gman_server);
      $gman_client->doBackground('ebidlh_suc_work',$job->workload());
    }
    catch(\Exception $e){
      $sub->close();
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

  public static function print_bid(BidKey $bidkey){
    $out=[];
    switch($bidkey->bidtype){
      case 'con': $out[]=Console::renderColoredString('%y[공사]%n'); break;
      case 'ser': $out[]=Console::renderColoredString('%g[용역]%n'); break;
      case 'pur': $out[]=Console::renderColoredString('%b[구매]%n'); break;
    }
    $out[]=$bidkey->constnm;
    $out[]="({$bidkey->notinum})";
    echo join(' ',$out),PHP_EOL;
  }
}

