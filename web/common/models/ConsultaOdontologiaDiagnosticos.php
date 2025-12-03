<?php

namespace common\models;

use Yii;

use common\models\snomed\SnomedHallazgos;

/**
 * This is the model class for table "consulta_odontologia_diagnosticos".
 *
 * @property int $id_consulta_odontologia_diagnosticos
 * @property int $id_consulta
 * @property int $pieza
 * @property string|null $caras
 * @property string $tipo 
 */
class ConsultaOdontologiaDiagnosticos extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public $term;

    const CONDICION_ACTIVO = 'ACTIVO';
    const CONDICION_INACTIVO = 'INACTIVO';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_odontologia_diagnosticos';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
        ];
    }
        
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'tipo', 'codigo'], 'required'],
            ['pieza', 'required', 'when' => function($model) {
                return $model->tipo == 'PIEZA';
            }],
            ['caras', 'required', 'when' => function($model) {
                return $model->tipo == 'CARAS';
            }],            
            [['id_consulta', 'pieza'], 'integer'],
            [['caras', 'tipo', 'codigo'], 'string'],            
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Tipo",
            "Codigo",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'Id',
            'id_consulta' => 'Id Consulta',
            'pieza' => 'Pieza',
            'caras' => 'Caras',            
            'tipo' => 'Tipo',            
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSnomedDiagnostico()
    {
        return $this->hasOne(SnomedHallazgos::className(), ['conceptId' => 'codigo']);
    }    

    public static function getPorPaciente($idPersona)
    {
        return self::find()
            ->select('consultas_odontologia_diagnosticos.*, snomed_hallazgos.term')
            ->innerJoin('consultas', 
                'consultas.id_consulta = consultas_odontologia_diagnosticos.id_consulta AND id_persona = '.$idPersona.
                ' AND consultas_odontologia_diagnosticos.condicion = "'.self::CONDICION_ACTIVO.'"'.' AND consultas.deleted_at IS NULL')
            ->innerJoin('snomed_hallazgos', 
                'consultas_odontologia_diagnosticos.codigo = snomed_hallazgos.conceptId')                
            ->asArray()
            ->all();
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
