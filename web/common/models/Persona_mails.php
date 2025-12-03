<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "persona_mails".
 *
 * @property string $id_persona_mail
 * @property integer $id_persona
 * @property string $mail
 *
 * @property Personas $idPersona
 */
class Persona_mails extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'persona_mails';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        /*return [
            //[['id_persona', 'mail'], 'required'],
             
            [['id_persona'], 'integer'],
            [ 'mail', 'default'],
             ['mail', 'email','message'=> 'Formato inválido'],
        ];*/
        return [
           // [['id_persona'], 'required'],
            [['id_persona'], 'integer'],
            ['mail', 'email','message'=> 'Formato inválido'],
            [['id_persona'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona' => 'id_persona']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_persona_mail' => 'Id Persona Mail',
            'id_persona' => 'Id Persona',
            'mail' => 'Mail',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersona()
    {
        return $this->hasOne(Personas::className(), ['id_persona' => 'id_persona']);
    }
    
    public function beforeSave($insert) {
        parent::beforeSave($insert);
        extract($_GET);

        $model_persona = new \common\models\Persona();
        $model_persona->load(Yii::$app->request->post());
             
        if ($insert) {
           if(isset($idp)){
                $this->id_persona = $idp;
            }else{
                $this->id_persona = $this->id_persona ;
            }
            
            }
        return true;
    }
}
