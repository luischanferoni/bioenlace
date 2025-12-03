<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "tipos_documentos".
 *
 * @property integer $id_tipodoc
 * @property string $nombre
 * @property string $comentario
 * @property string $habilitado
 *
 * @property Personas[] $personas
 */
class Tipo_documento extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tipos_documentos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_tipodoc', 'nombre', 'comentario', 'habilitado'], 'required'],
            [['id_tipodoc'], 'integer'],
            [['habilitado'], 'string'],
            [['nombre'], 'string', 'max' => 4],
            [['comentario'], 'string', 'max' => 50]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tipodoc' => 'Id Tipodoc',
            'nombre' => 'Nombre',
            'comentario' => 'Comentario',
            'habilitado' => 'Habilitado',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonas()
    {
        return $this->hasMany(Persona::className(), ['id_tipodoc' => 'id_tipodoc']);
    }
    
    public static function getListaTiposDocumento($tipo = 'INSCRIPCION')
    {
        if($tipo == 'INSCRIPCION'){
            $tipos_documento = static::find()->indexBy('id_tipodoc')->asArray()->all(); 
        } else {
            $tipos_documento = static::find()->where(['tipo' => $tipo])->orWhere(['tipo' => 'AMBOS'])->indexBy('id_tipodoc')->orderBy('nombre')->asArray()->all(); 
        }

       
       return \yii\helpers\ArrayHelper::map($tipos_documento, 'id_tipodoc', 
           function($model) {
                return $model['nombre'].' - '.$model['comentario'];
            });
    }
    public static function getTipoDocumento($id_tipodoc)
    {
        return static::findOne($id_tipodoc);
    }
}
