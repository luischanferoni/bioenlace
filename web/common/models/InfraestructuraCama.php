<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "infraestructura_cama".
 *
 * @property int $id
 * @property int|null $nro_cama
 * @property int|null $respirador
 * @property int|null $monitor
 * @property int $id_sala
 * @property string|null $estado
 *
 * @property InfraestructuraSala $sala
 * @property SegNivelInternacion[] $segNivelInternacions
 */
class InfraestructuraCama extends \yii\db\ActiveRecord
{
    public $id_piso;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'infraestructura_cama';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_sala'], 'required'],
            [['id', 'nro_cama', 'respirador', 'monitor', 'id_sala'], 'integer'],
            [['estado'], 'string'],
            [['id_sala'], 'exist', 'skipOnError' => true, 'targetClass' => InfraestructuraSala::className(), 'targetAttribute' => ['id_sala' => 'id']],
            
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nro_cama' => 'Nro Cama',
            'respirador' => 'Respirador',
            'monitor' => 'Monitor',
            'id_sala' => 'Sala',
            'id_piso' => 'Piso',
            'estado' => 'Estado',
        ];
    }

    /**
     * Gets query for [[Sala]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSala()
    {
        return $this->hasOne(InfraestructuraSala::className(), ['id' => 'id_sala']);
    }

    /**
     * Gets query for [[SegNivelInternacions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternaciones()
    {
        return $this->hasMany(SegNivelInternacion::className(), ['id_cama' => 'id']);
    }
    
    /**
     * Gets query for [[SegNivelInternacions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacionActual()
    {
        return $this->hasOne(SegNivelInternacion::className(), ['id_cama' => 'id'])
        ->onCondition(['<=', 'fecha_inicio', date('Y-m-d')])
        ->andOnCondition(['is', 'fecha_fin', NULL]);
    }
    /**
    * Obtiene la internacion en curso de la cama
    * mediante la cual se puede obtener el paciente 
    * internado en ella.
    */
    // public function getInternacionActual()
    // {
    //     return SegNivelInternacion::find()->where(['id_cama' => $this->id])
    //     ->andWhere(['<=', 'fecha_inicio', date('Y-m-d')])
    //     ->andWhere(['is', 'fecha_fin', NULL]);
        
    // }


    //PROBADOR DE ERRORES

    /*public function afterValidate()
    {
        $this->addError('*','probando error');
    }*/

}
