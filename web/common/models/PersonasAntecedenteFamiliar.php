<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "personas_antecedentes".
 *
 * @property integer $id_consulta
 * @property integer $id_antecedente
 * @property integer $id_snomed_situacion
 * @property string|null $deleted_at
 * @property Antecedentes $idAntecedente
 * @property Personas $idConsulta
 */
class PersonasAntecedenteFamiliar extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public $select2_codigo;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'personas_antecedentes';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta'], 'required'],
            [['id_consulta', 'id_antecedente','id_persona'], 'integer'],
            [['tipo_antecedente', 'origen_id_antecedente','codigo'], 'string'],
            ['select2_codigo', 'each', 'rule' => ['string']],
            [['id_antecedente'], 'default', 'value' => 0],
            [['codigo'], 'unique', 
                'targetAttribute' => ['tipo_antecedente', 'codigo', 'id_persona', 'deleted_at'], 
                'message' => 'El antecedente {value} ya se encuentra cargado para el paciente'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Id Consulta',
            'id_antecedente' => '',
            'id_snomed_situacion' => '',
            'select2_codigo' => 'Antecedentes Familiares',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdAntecedente()
    {
        return $this->hasOne(Antecedente::className(), ['id_antecedente' => 'id_antecedente']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSnomedSituacion()
    {
        return $this->hasOne(snomed\SnomedSituacion::className(), ['conceptId' => 'codigo']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdConsulta()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_consulta']);
    }
    
    //Busca los antecedentes familiares de una persona por consulta
    public static function getPersonasAntecedenteFamiliarPorConsulta($id_cons)
    {
       $personas_antecedente_familiar = PersonasAntecedenteFamiliar::find()
                                ->where(['tipo_antecedente' => 'Familiar'])
                                ->andWhere(['id_consulta'=>$id_cons])
                                ->andWhere(['deleted_at'=> NULL])
                                ->all();
       return $personas_antecedente_familiar;
               
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::hardDeleteAll([
                'AND',
                ['in', 'id', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }

}
