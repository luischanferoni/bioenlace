<?php

namespace common\models;

use common\models\snomed\SnomedProblemas;

use Yii;

/**
 * This is the model class for table "consultas_motivos".
 *
 * @property string $id_consulta
 * @property string $codigo
 * @property string $origen medico|paciente - quién cargó el motivo (médico en consulta o paciente vía app)
 * @property Consultas $idConsulta
 */
class ConsultaMotivos extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const ORIGEN_MEDICO = 'medico';
    const ORIGEN_PACIENTE = 'paciente';

    public $select2_codigo;
    public $terminos_motivos;
    public $id_servicio;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_motivos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // select2_codigo es para poder validar el select2
        // terminos_motivos es solamente para mantenerlo en el post entre paso uno a dos
        return [
            [['id_consulta', 'select2_codigo'], 'required'],
            [['id', 'id_consulta', 'id_servicio'], 'integer'],
            [['codigo', 'terminos_motivos', 'detalle'], 'string'],
            [['origen'], 'string', 'max' => 20],
            [['origen'], 'in', 'range' => [self::ORIGEN_MEDICO, self::ORIGEN_PACIENTE]],
            [['origen'], 'default', 'value' => self::ORIGEN_MEDICO],
            ['select2_codigo', 'each', 'rule' => ['string'],],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Consulta',
            'codigo' => 'Motivo',
            'origen' => 'Origen',
            'select2_codigo' => 'Motivos de consulta',
            'detalle' => 'Detalle',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consultas::className(), ['id_consulta' => 'id_consulta']);
    }


    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedProblemas::className(), ['conceptId' => 'codigo']);
    }

    /**
     * Antes de guardar, asegurar origen por defecto (medico) para compatibilidad.
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if (empty($this->origen) || !in_array($this->origen, [self::ORIGEN_MEDICO, self::ORIGEN_PACIENTE], true)) {
                $this->origen = self::ORIGEN_MEDICO;
            }
            return true;
        }
        return false;
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
