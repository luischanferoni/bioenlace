<?php

namespace frontend\modules\mapear\models;

use Yii;

class MapearActiveRecord extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        return Yii::$app->modules['mapear']->get('db');
    }

}