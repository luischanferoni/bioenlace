<?php

namespace common\models;

use common\models\Consulta;
use common\models\snomed\SnomedProcedimientos;
use Yii;

/**
 * This is the model class for table "consulta_practicas_oftalmologia".
 *
 * @property int $id
 * @property int|null $id_consulta
 * @property int|null $codigo
 * @property string|null $ojo
 * @property string|null $prueba
 * @property string|null $estado
 * @property string|null $resultado
 * @property string|null $informe
 * @property string|null $adjunto
 */
class ConsultaPracticasOftalmologiaEstudios extends ConsultaPracticasOftalmologia
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const SCENARIOTIPOGRUPO1 = 'scenario1';
    const SCENARIOTIPOGRUPO2 = 'scenario2';

    const ESTADO_EN_PREPARACION = 'PREPARATION';
    const ESTADO_EN_PROGRESO = 'IN-PROGRESS';
    const ESTADO_NO_REALIZADA = 'NOT-DONE';
    const ESTADO_EN_ESPERA = 'ON-HOLD';
    const ESTADO_DETENIDA = 'STOPPED';
    const ESTADO_COMPLETADA = 'COMPLETED';
    const ESTADO_INGRESADA_POR_ERROR = 'ENTERED-IN-ERROR';
    const ESTADO_DESCONOCIDO = 'UNKNOWN';

    const PRACTICA_TIPO_NUTRICION = 'NUTRICION';
    const PRACTICA_TIPO_IMAGENES = 'IMAGENES';
    const PRACTICA_TIPO_LABORATORIO = 'LABORATORIO';

    /**
     *
     *
     * Cuando proviene de una derivacion
     * permite cargar el Id de la derivacion para luego poder cambiar el estado a rechazado
     */
    public $id_consultas_derivaciones = 0;

    /**
     * @var bool
     *
     * Cuando proviene de una derivacion
     * permite deshabilitar la modificacion de la practica a realizar
     */
    public $codigo_deshabilitado = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consulta_practicas_oftalmologia';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'tipo', 'id_consultas_derivaciones'], 'integer'],
            [['ojo', 'prueba', 'estado', 'resultado', 'informe', 'adjunto', 'codigo'], 'string'],
            [['codigo','ojo','informe'], 'required'],
            [
                ['id_consulta'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Consulta::className(),
                'targetAttribute' => ['id_consulta' => 'id_consulta']
            ],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Codigo",
            "Ojo",
            "Informe",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_consulta' => 'Id Consulta',
            'codigo' => 'Practica',
            'ojo' => 'Ojo',
            'prueba' => 'Prueba',
            'estado' => 'Estado',
            'resultado' => 'Resultado',
            'informe' => 'Informe',
            'adjunto' => 'Adjunto',
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
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'codigo']);
        #return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'id_snomed_procedimiento']);
    }

    public function getTerm()
    {
        return is_null($this->codigoSnomed) ? '' : $this->codigoSnomed->term;
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
