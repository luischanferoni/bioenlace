<?php

namespace common\models;
use webvimark\modules\UserManagement\models\User;
use Yii;

/**
 * This is the model class for table "user_efector".
 *
 * @property integer $id_user
 * @property integer $id_efector
 *
 * @property User $idUser
 */
class UserEfector extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_efector';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_user', 'id_efector'], 'required'],
            [['id_user', 'id_efector'], 'integer']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_user' => 'Id User',
            'id_efector' => 'Id Efector',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'id_user']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }
}
