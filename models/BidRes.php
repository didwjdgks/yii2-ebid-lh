<?php
namespace ebidlh\models;

class BidRes extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_res';
  }

  public static function getDb(){
    return \ebidlh\Module::getInstance()->db;
  }

  public function rules(){
    return [
    ];
  }

  public function beforeSave($insert){
    if(parent::beforeSave($insert)){
      if($this->officenm1) $this->officenm1=iconv('utf-8','euckr',$this->officenm1);
      if($this->prenm1) $this->prenm1=iconv('utf-8','euckr',$this->prenm1);
      return true;
    }
    return false;
  }

  public function afterFind(){
    parent::afterFind();
    if($this->officenm1) $this->officenm1=iconv('euckr','utf-8',$this->officenm1);
    if($this->prenm1) $this->prenm1=iconv('euckr','utf-8',$this->prenm1);
  }
}

