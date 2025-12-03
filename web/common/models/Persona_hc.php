<?php

namespace common\models;

use Yii;


/**
 * This is the model class for table "personas_hc".
 *
 * @property integer $id_persona
 * @property integer $id_efector
 * @property integer $numero_hc
 */

class Persona_hc extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
  
    public static function tableName()
    {
        return 'personas_hc';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
   
        return [
            [['id_persona', 'numero_hc'], 'required'],
            [['id_persona', 'id_efector', 'numero_hc'], 'integer'],
            ['numero_hc', 'validarUnico'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_persona' => 'Persona',
            'id_efector' => 'Efector',
            'numero_hc' => 'N° de Historia Clínica',
        ];
    }
    
    public static function getHcPorPersona($id_persona){
    // Este metodo no está funcionando
        $query = new yii\db\Query;
        $query->select("numero_hc")
            ->from('personas_hc')
            ->where("id_persona = ".$id_persona)
            ->where("id_efector = ".Yii::$app->user->getIdEfector())
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();

        return $data['numero_hc'];

    }    

    public function validarUnico($attribute, $params)
    {

        $query = new yii\db\Query;
        $query->select(['COUNT(*) AS cnt'])
            ->from('personas_hc')
            ->where("id_efector = ".Yii::$app->user->getIdEfector())
            ->andWhere("numero_hc = ".$this->numero_hc)
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();

        if($data['cnt'] == 1){
            $this->addError($attribute, 'El numero ya existe en el sistema.');
        }
    }    


    public function beforeSave($insert) {
        $this->id_efector=Yii::$app->user->getIdEfector();
        return true;

}

}
