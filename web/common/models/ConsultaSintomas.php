<?php

namespace common\models;

use common\models\snomed\SnomedProblemas;
use Yii;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "consultas_sintomas".
 *
 * @property string $id_consulta
 * @property string $codigo
 * @property string $tipo_sintomas
 *
 * @property Cie10 $codigo0
 * @property Consultas $idConsulta
 */
class ConsultaSintomas extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    use ParameterQuestionsTrait;

    public $select2_codigo;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_sintomas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'select2_codigo', 'codigo'], 'required'],
            [['id_consulta'], 'integer'],
            [['codigo'], 'string'],
            ['select2_codigo', 'each', 'rule' => ['string']],
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
            'codigo' => 'Sintoma',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'sintoma' => '¿Qué síntoma tenés?',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedProblemas::className(), ['conceptId' => 'codigo']);
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
