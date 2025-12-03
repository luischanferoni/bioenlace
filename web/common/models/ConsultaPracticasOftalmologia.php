<?php

namespace common\models;

use yii\validators\NumberValidator;

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
class ConsultaPracticasOftalmologia extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const SCENARIOTIPOGRUPO1 = 'scenario1';
    const SCENARIOTIPOGRUPO2 = 'scenario2';

    const PRACTICAS_EVALUACION = [
        252832004 => 'Presión intraocular',
        252886007 => 'Refracción',
        55468007 => 'Lámpara de hendidura',
        410455004 => 'Fondo de ojo con lámpara de hendidura',
        16830007 => 'evaluación de agudeza visual'
    ];

    const PRACTICAS_OFTALMOLOGICAS = [
        164729009 => 'Tonometría',
        410453006 => 'Oftalmoscopia Binocular Indirecta',
        55468007 => 'Biomicroscopia',
        252886007 => 'Refracción',
        48706002 => 'Exoftalmologia'
    ];

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
            [['ojo', 'resultado'], 'required', 'on' => self::SCENARIOTIPOGRUPO1],
            [['ojo', 'informe'], 'required', 'on' => self::SCENARIOTIPOGRUPO2],
            [['resultado'], 'validateValores'],
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
        return [];
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

    public function validateValores($attribute, $params, $validator)
    {

        if ($this->codigo == 164729009) {
            $tonometria_validator = new NumberValidator();
            $tonometria_validator->min = 0;
            $tonometria_validator->message = 'El resultado de esta practica debe ser un numero';
            $tonometria_validator->tooSmall = 'El resultado de esta practica no debe ser menor a 0';

            if (!$tonometria_validator->validate($this->$attribute, $error)) {
                $this->addError($attribute, $error);
            }
        }

        /*if ($this->codigo == 252886007) {
            $refraccion_validator = new NumberValidator();
            $refraccion_validator->min = -25;
            $refraccion_validator->max = 25;
            $refraccion_validator->message = 'El resultado de esta practica debe ser un numero';
            $refraccion_validator->tooSmall = 'El resultado de esta practica no debe ser menor a -25';
            $refraccion_validator->tooSmall = 'El resultado de esta practica no debe ser mayor a +25';

            if (!$refraccion_validator->validate($this->$attribute, $error)) {
                $this->addError($attribute, $error);
            } else {
                $division = $this->$attribute / 0.25;
                $division = intval($division);
                $resto = $this->$attribute - ($division * 0.25);
                if ($resto != 0):
                    $this->addError($attribute, 'Debe ser divisible por 0.25');
                endif;
            }
        }*/

        if($this->codigo == 16830007){
            return true;
        }
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
