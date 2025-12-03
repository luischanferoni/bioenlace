<?php

namespace common\models;

use yii\db\Query;
use common\models\snomed\SnomedProcedimientos;
use common\models\snomed\SnomedHallazgos;

use Yii;

/**
 * This is the model class for table "consulta_odontologia_practicas".
 *
 * @property int $id_consulta_odontologia_practicas
 * @property int $id_consulta
 * @property int $pieza
 * @property string|null $caras
 * @property string $tipo
 */
class ConsultaOdontologiaPracticas extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const TIEMPO_PASADA = 'PASADA';
    const TIEMPO_PRESENTE = 'PRESENTE';
    const TIEMPO_FUTURA = 'FUTURA';

    const TIEMPOS = [
            self::TIEMPO_PASADA => ['title' => 'Estado Inicial', 'btn_anterior' => false, 'btn_siguiente' => 'Continuar con Prácticas Actuales', 'tiempo_anterior' => false],
            self::TIEMPO_PRESENTE => ['title' => 'Practicas realizadas', 'btn_anterior' => 'Volver', 'btn_siguiente' => 'Continuar con Practicas a planificar', 'tiempo_anterior' => self::TIEMPO_PASADA],
            self::TIEMPO_FUTURA => ['title' => 'Practicas planificadas', 'btn_anterior' => 'Volver', 'btn_siguiente' => 'Finalizar', 'tiempo_anterior' => self::TIEMPO_PRESENTE]
        ];
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_odontologia_practicas';
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
            ['caras', 'required', 'when' => function($model) {
                return $model->tipo == 'CARAS';
            }],            
            [['id_consulta', 'pieza'], 'integer'],
            [['caras', 'codigo', 'tipo', 'tiempo', 'diagnostico'], 'string'],
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
            'id_consultas_odontologia_practicas' => 'Id',
            'id_consulta' => 'Id Consulta',
            'pieza' => 'Pieza',
            'caras' => 'Caras',
            'tipo' => 'Tipo',
            'codigo' => 'Codigo',
            'diagnostico' => 'Diagnostico'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOdontoNomenclador()
    {
        return $this->hasOne(OdontoNomenclador::className(), ['codigo_faco' => 'codigo']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSnomedDiagnostico()
    {
        return $this->hasOne(SnomedHallazgos::className(), ['conceptId' => 'diagnostico']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSnomedPractica()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'codigo']);
    }

    public static function getPorPaciente($idPersona, $tiempo)
    {
        $sq0 = (new Query())
            ->select('max(dcy.id_consultas_odontologia_practicas) as id_consultas_odontologia_practicas, dcx.id_consulta')
            ->from(['dcx' => 'consultas_odontologia_practicas'])
            ->innerJoin(
                ['dcy' => 'consultas_odontologia_practicas'],
                'dcx.id_consultas_odontologia_practicas = dcy.root_id')
            ->innerJoin('consultas', 
                'consultas.id_consulta = dcx.id_consulta AND id_persona = '.$idPersona . ' AND consultas.deleted_at IS NULL')
            ->groupBy('dcx.id_consultas_odontologia_practicas');

        $sq1 = (new Query())
            ->select('cda.id_consultas_odontologia_practicas as id_consultas_odontologia_practicas, cda.id_consulta')
            ->from(['cda' => 'consultas_odontologia_practicas'])
            ->leftJoin(
                ['cdb' => 'consultas_odontologia_practicas'],
                'cda.id_consultas_odontologia_practicas = cdb.root_id')
            ->innerJoin('consultas', 
                'consultas.id_consulta = cda.id_consulta AND id_persona = '.$idPersona . ' AND consultas.deleted_at IS NULL')
            ->where('cda.root_id IS NULL')
            ->andWhere('cdb.root_id IS NULL');

        $mq = self::find()
                ->from(["dcm" => 'consultas_odontologia_practicas'])
                ->innerJoin(
                    ['v' => $sq0->union($sq1)],
                    'v.id_consultas_odontologia_practicas = dcm.id_consultas_odontologia_practicas');

        return $mq->asArray()->all();
            /*self::find()
            ->select('consultas.id_rr_hh, consultas_odontologia_practicas.*')
            ->innerJoin('consultas_odontologia_practicas', 
                'consultas_odontologia_practicas.id_consultas_odontologia_practicas = consultas_odontologia_practicas.root_id')
            ->innerJoin('consultas', 
                'consultas.id_consulta = consultas_odontologia_practicas.id_consulta AND id_persona = '.$idPersona)
            //->where(['tiempo' => $tiempo])
            ->asArray()
            ->all();*/
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
                ['in', 'id_consultas_odontologia_practicas', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }     
}
