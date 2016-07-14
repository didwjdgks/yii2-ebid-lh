<?php
namespace ebidlh\workers;

use yii\helpers\Json;
use yii\helpers\Console;
use yii\helpers\ArrayHelper;

use ebidlh\PassException;
use ebidlh\models\BidKey;

class BidWorker extends \yii\base\Object
{
  const URL_CON='http://ebid.lh.or.kr/ebid.et.tp.cmd.BidConstructDetailListCmd.dev';
  const URL_SER='http://ebid.lh.or.kr/ebid.et.tp.cmd.BidsrvcsDetailListCmd.dev';
  const URL_PUR='http://ebid.lh.or.kr/ebid.et.tp.cmd.BidgdsDetailListCmd.dev';
  const URL_PUR2='http://ebid.lh.or.kr/ebid.et.tp.cmd.BidctrctgdsDetailListCmd.dev'; //지급자재

  public static function work($job){
    $workload=$job->workload();
    echo $workload,PHP_EOL;
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

    try {
      if(ArrayHelper::keyExists('bidproc',$workload)){
        switch($workload['bidproc']){
          case '취소공고': $bidproc='C'; break;
          //case '연기공고': $bidproc='L'; break;
          default: $bidproc='B';
        }
        $bidkey=BidKey::findOne([
          'whereis'=>'05',
          'notinum'=>$workload['notinum'].'-'.$workload['subno'],
          'bidproc'=>$bidproc,
        ]);
        if($bidkey!==null){
          throw new PassException;
        }
      }

      switch($workload['bidtype']){
        case '공사': $url=self::URL_CON; break;
        case '용역': $url=self::URL_SER; break;
        case '물품': $url=self::URL_PUR; break;
        case '지급자재': $url=self::URL_PUR2; break;
        default: throw new \Exception('invalid bidtype');
      }

      $pub->publish('ebidlh-bid',[
        'cmd'=>'open-work',
        'channel'=>$channel,
      ]);

      $sub->subscribe([$channel],function ($redis,$chan,$msg) use ($pub,$channel,$workload,$url){
        if($msg==='ready'){
          $pub->publish($channel.'-client',[
            'url'=>$url,
            'post'=>http_build_query([
              'bidNum'=>$workload['notinum'],
              'bidDegree'=>$workload['subno'],
              'cstrtnJobGbCd'=>$workload['param3'],
              'emrgncyOrder'=>$workload['param4'],
            ]),
          ]);
          return;
        }
        $html=iconv('euckr','utf-8//IGNORE',$msg);
        $html=strip_tags($html,'<th><tr><td><a>');
        $html=preg_replace('/<th[^>]*>/i','<th>',$html);
        if(strpos($html,'한국정보인증(주)의 보안S/W를 설치중입니다')>0) return;
        $html=preg_replace('/<tr[^>]*>/i','<tr>',$html);
        $html=preg_replace('/<td[^>]*>/i','<td>',$html);
        $data=[];
        $files=[];

        $p='#<tr>'.
            ' <td>(?<ftype>[^<]*)</td>'.
            ' <td><a.*fn_dds_open\(\'\d+\',\'(?<rname>[^\']*)\',[^>]*>(?<fname>[^<]*)</a></td>'.
           ' </tr>#i';
        $p=str_replace(' ','\s*',$p);
        if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
          foreach($matches as $m){
            $fname=trim($m['fname']);
            $rname=trim($m['rname']);
            $files[]="$fname#('bidinfo','$fname','$rname')";
          }
        }
        $data['attchd_lnk']=implode('|',$files);

        $html=strip_tags($html,'<th><tr><td>');
        //공고종류
        $p='#공고종류</th> <td>(?<bidproc>[^<]*)</td>#i';
        $data['bidproc']=self::match($p,$html,'bidproc');
        //공고명
        $p='#입찰공고건명</th> <td>(?<constnm>.+)본 공고는 지문인식#i';
        $data['constnm']=self::match($p,$html,'constnm');
        //공고부서
        $p='#공고부서</th> <td>(?<org>[^<]*)</td>#i';
        $data['org']=self::match($p,$html,'org');
        //추정가격
        $p='#추정가격</th> <td>(?<presum>\d+(,\d{1,3})*)원#i';
        $data['presum']=self::match($p,$html,'presum');
        //기초금액
        $p='#기초금액</th> <td>(?<basic>\d+(,\d{1,3})*)원#i';
        $data['basic']=self::match($p,$html,'basic');
        //계약방법
        $p='#계약방법</th> <td>(?<contract>[^<]*)</td>#i';
        $data['contract']=self::match($p,$html,'contract');
        //입찰방식
        $p='#입찰방식</th> <td>(?<bidcls>[^<]*)</td>#i';
        $data['bidcls']=self::match($p,$html,'bidcls');
        //낙찰자선정방법
        $p='#낙찰자선정방법</th> <td>(?<succls>[^<]*)</td>#i';
        $data['succls']=self::match($p,$html,'succls');
        //공동수급협정서접수마감일시
        $p='#공동수급협정서접수마감일시</th> <td>(?<hyupenddt>[^<]*)</td>#i';
        $data['hyupenddt']=self::match($p,$html,'hyupenddt');
        //입찰서접수개시일시
        $p='#입찰서접수개시일시</th> <td>(?<opendt>[^<]*)</td>#i';
        $data['opendt']=self::match($p,$html,'opendt');
        //입찰서접수마감일시
        $p='#입찰서접수마감일시</th> <td>(?<closedt>[^<]*)</td>#i';
        $data['closedt']=self::match($p,$html,'closedt');
        //입찰참가신청서접수마감일시
        $p='#입찰참가신청서접수마감일시</th> <td>(?<registdt>[^<]*)</td>#i';
        $data['registdt']=self::match($p,$html,'registdt');
        //개찰일시
        $p='#개찰일시</th> <td>(?<constdt>[^<]*)</td>#i';
        $data['constdt']=self::match($p,$html,'constdt');
        //현장설명일시
        $p='#현장설명일시</th> <td>(?<explaindt>[^<]*)</td>#i';
        $data['explaindt']=self::match($p,$html,'explaindt');
        //공고변경사유
        $p='#공고변경사유</th> <td>(?<bidcomment_mod>[^<]*)</td>#i';
        $data['bidcomment_bid']=self::match($p,$html,'bidcomment_mod');
        
        //투찰제한
        $p='#투찰제한정보 <tr>'.
            ' <th>참가지역1</th> <td>(?<local1>[^<]*)</td>'.
            ' <th>참가지역2</th> <td>(?<local2>[^<]*)</td>'.
            ' <th>참가지역3</th> <td>(?<local3>[^<]*)</td>'.
            ' <th>참가지역4</th> <td>(?<local4>[^<]*)</td>'.
           ' </tr>#i';
        $p=str_replace(' ','\s*',$p);
        if(preg_match($p,$html,$m)){
          $data['local1']=trim($m['local1']);
          $data['local2']=trim($m['local2']);
          $data['local3']=trim($m['local3']);
          $data['local4']=trim($m['local4']);
        }
        //지역의무공동업체제한
        $p='#지역의무공동업체제한 <tr>'.
            ' <th>참가지역1</th> <td>(?<local1>[^<]*)</td>'.
            ' <th>참가지역2</th> <td>(?<local2>[^<]*)</td>'.
            ' <th>참가지역3</th> <td>(?<local3>[^<]*)</td>'.
            ' <th>참가지역4</th> <td>(?<local4>[^<]*)</td>'.
           ' </tr>#i';
        $p=str_replace(' ','\s*',$p);
        if(preg_match($p,$html,$m)){
          $data['contloc1']=trim($m['local1']);
          $data['contloc2']=trim($m['local2']);
          $data['contloc3']=trim($m['local3']);
          $data['contloc4']=trim($m['local4']);
        }

        print_r($data);

        if(strpos($data['bidproc'],'취소공고')!==false){
          $bidproc='C';
        }else{
          $bidproc='B';
        }
        $bidkey=BidKey::findOne([
          'whereis'=>'05',
          'notinum'=>$workload['notinum'].'-'.$workload['subno'],
          'bidproc'=>$bidproc,
        ]);
        if($bidkey===null){
          if(strpos($data['bidproc'],'취소공고')!==false){
            $bidproc='C';
          }
        }

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
      echo Console::renderColoredString('%r'.$e->getMessage().'%n'),PHP_EOL;
      $gman_client=new \GearmanClient;
      $gman_client->addServers($module->gman_server);
      $gman_client->doBackground('ebidlh_bid_work',$job->workload());
    }
    catch(\Exception $e){
      $sub->close();
      echo Console::renderColoredString("%r$e%n"),PHP_EOL;
      \Yii::error($job->workload()."\n".$e,'ebidlh');
    }

    $module->db->close();
    echo Console::renderColoredString("%c".
          sprintf("[%s] Peak memory usage: %sMb",
            date('Y-m-d H:i:s'),
            (memory_get_peak_usage(true)/1024/1024)
          )."%n"
    ),PHP_EOL;
  }

  public static function match($pattern,$html,$label){
    $pattern=str_replace(' ','\s*',$pattern);
    if(preg_match($pattern,$html,$m)){
      return trim($m[$label]);
    }
    return '';
  }
}

