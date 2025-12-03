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
 * @property string|null $deleted_by
 * @property Antecedentes $idAntecedente
 * @property Personas $idConsulta
 */
class PersonasAntecedente extends \yii\db\ActiveRecord
{

    use \common\traits\SoftDeleteDateTimeTrait; // NO USAR PORQUE LA BAJA FISICA SI DEBE EXISTIR
    // MIENTRAS SE PUEDE EDITAR LA CONSULTA YA QUE LA BAJA LOGICA SE USA PARA LA EDICION DE LA CONSULTA ENTERA
    public $terminos_motivos;
    public $id_servicio;
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
            [['id_consulta', 'id_antecedente','id_persona','id_servicio'], 'integer'],
            [['tipo_antecedente', 'origen_id_antecedente','codigo','terminos_motivos'], 'string'],
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
            'select2_codigo' => 'Antecedentes Personales',
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
    
    /**
     * getParent hace referencia al vínculo con x clase,
     * se usan las propiedades parent y parent_id
     */    
    public function getParent()
    {
        return $this->hasOne($this->parent_class, ['id' => 'parent_id']);
    }
        
    //Busca los antecedentes de una persona por consulta
    public static function getPersonasAntecedentePorConsulta($id_cons)
    {
       $personas_antecedente = PersonasAntecedente::find()
                                ->where(['tipo_antecedente' => 'Personal'])
                                ->andWhere(['id_consulta'=>$id_cons])
                                ->andWhere(['deleted_at'=> NULL])
                                ->all();
       return $personas_antecedente;
    }

    public static function getPersonasAntecedentePorSnomed($id_persona, $codigo_snomed, $tipo)
    {
       $persona_antecedente = PersonasAntecedente::find()
                                ->where(['id_persona' => $id_persona])
                                ->andWhere(['codigo' => $codigo_snomed])
                                ->andWhere(['tipo_antecedente' => $tipo])
                                ->all();
       return $persona_antecedente;
    }


    public function beforeSave($insert) {
         
        if ($this->isRelationPopulated('parent')) {
            $this->parent_class = get_class($this->parent);            
        }

        return true;
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
