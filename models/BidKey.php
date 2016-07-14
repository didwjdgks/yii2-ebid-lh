<?php
namespace ebidlh\models;

class BidKey extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_key';
  }

  public static function getDb(){
    return \ebidlh\Module::getInstance()->db;
  }

  public function rules(){
    return [
    ];
  }

  public function saveCancelProc(){
    if(!$this->isNewRecord) return false;
  }

  public function beforeSave($insert){
    if(parent::beforeSave($insert)){
      if($this->notinum) $this->notinum=iconv('utf-8','euckr',$this->notinum);
      if($this->constnm) $this->constnm=iconv('utf-8','euckr',$this->constnm);
      if($this->org) $this->org=iconv('utf-8','euckr',$this->org);
      if($this->org_i) $this->org_i=iconv('utf-8','euckr',$this->org_i);
      if($this->org_y) $this->org_y=iconv('utf-8','euckr',$this->org_y);
      return true;
    }
    return false;
  }

  public function afterFind(){
    parent::afterFind();
    if($this->notinum) $this->notinum=iconv('euckr','utf-8',$this->notinum);
    if($this->constnm) $this->constnm=iconv('euckr','utf-8',$this->constnm);
    if($this->org) $this->org=iconv('euckr','utf-8',$this->org);
    if($this->org_i) $this->org_i=iconv('euckr','utf-8',$this->org_i);
    if($this->org_y) $this->org_y=iconv('euckr','utf-8',$this->org_y);
  }

  public function getBidValue(){
    return $this->hasOne(BidValue::className(),['bidid'=>'bidid']);
  }

  public function getBidRes(){
    return $this->hasOne(BidRes::className(),['bidid'=>'bidid']);
  }
}

