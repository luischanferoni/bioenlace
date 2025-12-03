<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "persona_telefono".
 *
 * @property string $id_persona_telefono
 * @property integer $id_persona
 * @property string $id_tipo_telefono
 * @property string $numero
 * @property string $comentario
 *
 * @property Personas $idPersona
 * @property TipoTelefono $idTipoTelefono
 */
class PersonaTelefono extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */

    
    public static function tableName()
    {
        return 'persona_telefono';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
             [[ 'id_tipo_telefono', 'numero'], 'required'],
            [['id_persona', 'id_tipo_telefono'], 'integer'],
            [['comentario'], 'string'],
            [['numero'], 'string', 'max' => 20]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_persona_telefono' => 'Id Persona Telefono',
            'id_persona' => 'Id Persona',
            'id_tipo_telefono' => 'Tipo de Telefono',
            'numero' => 'Numero',
            'comentario' => 'Comentario',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTipoTelefono()
    {
        return $this->hasOne(Tipo_telefono::className(), ['id_tipo_telefono' => 'id_tipo_telefono']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTelefonosPorPersona($id_persona)
    {
//         $telefonos_persona = static::find()->indexBy('id_persona_telefono')->asArray()->all(); 
       $sql='SELECT * FROM `persona_telefono` 
                WHERE  `persona_telefono`.`id_persona` = '.$id_persona.'';
        $telefonos=  PersonaTelefono::findBySql($sql)->asArray()->all();
        return $telefonos;
    }
    
    
    public function beforeSave($insert) {
        parent::beforeSave($insert);
        extract($_GET);

        var_dump( $model_persona = new \common\models\Persona());
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
