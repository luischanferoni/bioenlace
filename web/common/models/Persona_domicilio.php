<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "personas_domicilios".
 *
 * @property string $id_domicilio
 * @property integer $id_persona
 * @property string $activo
 * @property string $usuario_alta
 * @property string $fecha_alta
 *
 * @property Personas $idPersona
 * @property Domicilios $idDomicilio
 */
class Persona_domicilio extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'personas_domicilios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_domicilio', 'id_persona'], 'required'],
            [['id_domicilio', 'id_persona'], 'integer'],
            [['activo'], 'string'],
            [['fecha_alta'], 'safe'],
            [['usuario_alta'], 'string', 'max' => 40]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_domicilio' => 'Id Domicilio',
            'id_persona' => 'Id Persona',
            'activo' => 'Activo',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
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
    public function getDomicilio()
    {
        return $this->hasOne(Domicilio::className(), ['id_domicilio' => 'id_domicilio']);
    }
    
    
    public function beforeSave($insert) {
        parent::beforeSave($insert);
        
        $model = new Persona_domicilio();
        $model_persona = new Persona();
        $model_domicilio = new Domicilio();
        
        $model_domicilio->load(Yii::$app->request->post());
        $model_persona->load(Yii::$app->request->post());
        extract($_GET);
        if ($insert) {
             if($model->isNewRecord){
                if(isset($idp)){
                     $this->id_persona = $idp;
                 }else{
                     $this->id_persona = $this->id_persona ;
                 }
            $this->id_domicilio = $this->id_domicilio;
            $this->fecha_alta = date("Y-m-d");
             }
            }     
        
        return true;
    }
    
    
    
}
