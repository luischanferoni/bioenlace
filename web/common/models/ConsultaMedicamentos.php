<?php


/**
 * @autor: Ivana Beltrán y María de los A. Valdez
 * @versión: 1.1 
 * @creacion: 25/02/2016
 * @modificacion: 
 **/

namespace common\models;

use common\models\snomed\SnomedMedicamentos;
use Yii;

/**
 * This is the model class for table "medicamentos_consultas".
 *
 * @property string $id_consulta
 * @property integer $id_medicamento
 * @property integer $cantidad
 * @property string $frecuencia
 * @property integer $id_snomed_medicamento
 * @property Consultas $idConsulta
 * @property Medicamentos $idMedicamento
 */
class ConsultaMedicamentos extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    
    const ESTADO_SUSPENDIDO = 'SUSPENDIDO';
    const ESTADO_INGRESADO_POR_ERROR = 'INGRESADO_POR_ERROR';
    const ESTADO_ACTIVO = 'ACTIVO';

    const ESTADOS = [self::ESTADO_ACTIVO => 'Activo', self::ESTADO_SUSPENDIDO => 'Suspendido', self::ESTADO_INGRESADO_POR_ERROR => 'Ingresado por Error'];

    const FRECUENCIA_TIPO_MINUTO = 'MINUTO';
    const FRECUENCIA_TIPO_HORA = 'HORA';
    const FRECUENCIA_TIPO_DIA = 'DIA';
    const FRECUENCIAS = [self::FRECUENCIA_TIPO_MINUTO => 'Minuto', self::FRECUENCIA_TIPO_HORA => 'Hora', self::FRECUENCIA_TIPO_DIA => 'Día'];

    const DURANTE_TIPO_DIA = 'DIA';
    const DURANTE_TIPO_SEMANA = 'SEMANA';
    const DURANTE_TIPO_MES = 'MES';    
    const DURANTE_TIPO_CRONICO = 'CRONICO';
    const DURANTES = [self::DURANTE_TIPO_DIA => 'Día', self::DURANTE_TIPO_SEMANA => 'Semana', self::DURANTE_TIPO_MES => 'Mes', self::DURANTE_TIPO_CRONICO => 'Crónico'];

    public $terminos_motivos;
    public $id_servicio;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_medicamentos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'id_consultas_diagnosticos', 'id_snomed_medicamento', 'cantidad', 'frecuencia', 'frecuencia_tipo', 'durante', 'durante_tipo'], 'required'],
            [['id_consulta', 'id_consultas_diagnosticos', 'id_medicamento', 'frecuencia', 'durante', 'id_servicio'], 'integer'],
            [['cantidad'], 'double'],
            [['id_medicamento'], 'default', 'value' => 0],
            [['id_snomed_medicamento', 'indicaciones', 'estado', 'terminos_motivos'], 'string'],
            [['id_snomed_medicamento'], 'unique', 
                'targetAttribute' => ['id_consulta', 'id_snomed_medicamento'], 
                'message' => 'El medicamento ya esta cargado para esta consulta'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Nombre del medicamento",
            "Cantidad",
            "Frecuencia de administracion",
            "Tipo de frecuencia",
            "Duracion del tratamiento",
            "Tipo de duracion"
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_consulta' => 'Id Consulta',
            'id_consultas_diagnosticos' => 'Id Diagnóstico',
            'id_medicamento' => '',
            'id_snomed_medicamento' => '',
            'cantidad' => 'Cantidad',
            'frecuencia' => 'Cada',
            'durante' => 'Durante',
            'durante_tipo' => 'Unidad durante',
            'indicaciones' => 'Indicaciones',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     * TODO: refactorizar codigo para poder borrar este metodo y dejar el de abajo (getConsulta)
     */
    public function getIdConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
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
    public function getIdMedicamento()
    {
        return $this->hasOne(Medicamento::className(), ['id_medicamento' => 'id_medicamento']);
    }

    //Busca los medicamentos por consulta
    public static function getMedicamentosPorConsulta($id_cons)
    {
        $medicamentos = ConsultaMedicamentos::findAll(['id_consulta' => $id_cons]);
        return $medicamentos;
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSnomedMedicamento()
    {
        return $this->hasOne(SnomedMedicamentos::className(), ['conceptId' => 'id_snomed_medicamento']);
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

          /**
     * Gets query for [[Consulta]].
     *
     * @return \yii\db\ActiveQuery
     */
    public static function getMedicamentos($id_internacion) {
        return ConsultaMedicamentos::find()
                ->join('JOIN','consultas', '`consultas`.`id_consulta` = `consultas_medicamentos`.`id_consulta`')
                ->where(['consultas.parent_class' => '\common\models\SegNivelInternacion'])
                ->andWhere(['consultas.parent_id' => $id_internacion])
                ->all();
    }
}
