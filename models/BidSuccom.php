<?php
namespace ebidlh\models;

class BidSuccom extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_succom';
  }

  public static function getDb(){
    return \ebidlh\Module::getInstance()->db;
  }

  public static function primaryKey(){
    return ['bidid','seq'];
  }

  public function rules(){
    return [
    ];
  }

  public function beforeSave($insert){
    if(parent::beforeSave($insert)){
      if($this->officenm) $this->officenm=iconv('utf-8','euckr',$this->officenm);
      if($this->prenm) $this->prenm=iconv('utf-8','euckr',$this->prenm);
      return true;
    }
    return false;
  }

  public function afterFind(){
    parent::afterFind();
    if($this->officenm) $this->officenm=iconv('euckr','utf-8',$this->officenm);
    if($this->prenm) $this->prenm=iconv('euckr','utf-8',$this->prenm);
  }
}

