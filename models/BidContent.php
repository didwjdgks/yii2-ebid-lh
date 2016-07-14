<?php
namespace ebidlh\models\i2;

class BidContent extends \yii\db\ActiveRecord
{
  public static function tableName(){
    return 'bid_content';
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
      if($this->bidcomment_mod) $this->bidcomment_mod=iconv('utf-8','euckr//IGNORE',$this->bidcomment_mod);
      if($this->bidcomment)     $this->bidcomment=    iconv('utf-8','euckr//IGNORE',$this->bidcomment);
      if($this->bid_html)       $this->bid_html=      iconv('utf-8','euckr//IGNORE',$this->bid_html);
      if($this->nbidcomment)    $this->nbidcomment=   iconv('utf-8','euckr//IGNORE',$this->nbidcomment);
      if($this->nbid_html)      $this->nbid_html=     iconv('utf-8','euckr//IGNORE',$this->nbid_html);
      if($this->bid_file)       $this->bid_file=      iconv('utf-8','euckr//IGNORE',$this->bid_file);
      if($this->nbid_file)      $this->nbid_file=     iconv('utf-8','euckr//IGNORE',$this->nbid_file);
      return true;
    }
    return false;
  }

  public function afterFind(){
    parent::afterFind();
    if($this->bidcomment_mod) $this->bidcomment_mod=iconv('euckr','utf-8//IGNORE',$this->bidcomment_mod);
    if($this->bidcomment)     $this->bidcomment=    iconv('euckr','utf-8//IGNORE',$this->bidcomment);
    if($this->bid_html)       $this->bid_html=      iconv('euckr','utf-8//IGNORE',$this->bid_html);
    if($this->nbidcomment)    $this->nbidcomment=   iconv('euckr','utf-8//IGNORE',$this->nbidcomment);
    if($this->nbid_html)      $this->nbid_html=     iconv('euckr','utf-8//IGNORE',$this->nbid_html);
    if($this->bid_file)       $this->bid_file=      iconv('euckr','utf-8//IGNORE',$this->bid_file);
    if($this->nbid_file)      $this->nbid_file=     iconv('euckr','utf-8//IGNORE',$this->nbid_file);
  }
}


