<?php
namespace ebidlh\models;

class BidValue extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_value';
  }

  public static function getDb(){
    return \ebidlh\Module::getInstance()->db;
  }

  public function rules(){
    return [
    ];
  }

  public function afterFind(){
    parent::afterFind();
    if($this->constno)      $this->constno    =iconv('euckr','utf-8',$this->constno);
    if($this->refno)        $this->refno      =iconv('euckr','utf-8',$this->refno);
    if($this->realorg)      $this->realorg    =iconv('euckr','utf-8',$this->realorg);
    if($this->charger)      $this->charger    =iconv('euckr','utf-8',$this->charger);
    if($this->promise_org)  $this->promise_org=iconv('euckr','utf-8',$this->promise_org);
    if($this->contloc)      $this->contloc    =iconv('euckr','utf-8',$this->contloc);
  }

  public function beforeSave($insert){
    if(parent::beforeSave($insert)){
      if($this->constno)      $this->constno    =iconv('utf-8','euckr',$this->constno);
      if($this->refno)        $this->refno      =iconv('utf-8','euckr',$this->refno);
      if($this->realorg)      $this->realorg    =iconv('utf-8','euckr',$this->realorg);
      if($this->charger)      $this->charger    =iconv('utf-8','euckr',$this->charger);
      if($this->promise_org)  $this->promise_org=iconv('utf-8','euckr',$this->promise_org);
      if($this->contloc)      $this->contloc    =iconv('utf-8','euckr',$this->contloc);
      return true;
    }
    return false;
  }
}

