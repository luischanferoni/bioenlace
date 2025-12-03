<?php

namespace common\models\file;

use Yii;
use yii\base\Model;

class CSVForm extends Model {
    public $archivo;
   
    public function rules(){
        return [
            [['archivo'], 'required'],
            [['archivo'], 'file', 'extensions'=>'csv', 'maxSize'=> 1024 * 1024 * 5],
        ];
    }
   
    public function attributeLabels(){
        return [
            'archivo' => 'Archivo CSV',
        ];
    }
}