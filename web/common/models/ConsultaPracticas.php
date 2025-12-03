<?php

namespace common\models;
use common\models\snomed\SnomedProcedimientos;
use Yii;

/**
 * This is the model class for table "consultas_practicas".
 *
 * @property string $id_practicas_personas
 * @property integer $id_persona
 * @property string $ id_detalle_practicas
 * @property string $fecha
 *
 * @property Personas $idPersona
 */
class ConsultaPracticas extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    
    public $select2_codigo;

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
    const PRACTICA_TIPO_PROCEDIMIENTOS_ELECTROCARDIOGRAFICOS = 'PROCEDIMIENTOS_ELECTROCARDIOGRAFICOS';

    const PRACTICAS_TIPOS = [
        self::PRACTICA_TIPO_NUTRICION => 'Nutrición', 
        self::PRACTICA_TIPO_IMAGENES => 'Imágenes', 
        self::PRACTICA_TIPO_LABORATORIO => 'Laboratorio', 
        self::PRACTICA_TIPO_PROCEDIMIENTOS_ELECTROCARDIOGRAFICOS => 'Procedimientos Electrocardiograficos',
    ];

    public $terminos_motivos;
    public $id_servicio;
    /**
     * @var UploadedFile[]
     */
    public $archivos_adjuntos;

    /**
     * @var bool
     * 
     * Cuando proviene de una derivacion
     * permite deshabilitar la modificacion de la practica a realizar
     */
    public $codigo_deshabilitado = false;

    /**
     *
     *
     * Cuando proviene de una derivacion
     * permite cargar el Id de la derivacion para luego poder cambiar el estado a rechazado
     */
    public $id_consultas_derivaciones = 0;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_practicas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_consulta', 'codigo'], 'required'],
            ['select2_codigo', 'each', 'rule' => ['string']],
            [['id', 'id_detalle_practicas', 'id_consultas_diagnosticos', 'id_consultas_derivaciones','id_servicio'], 'integer'],
            [['codigo'], 'unique', 'targetAttribute' => ['id_consulta', 'codigo']],
            [['tipo', 'informe', 'codigo','estado', 'adjunto', 'terminos_motivos'], 'string'],
            [['archivos_adjuntos'], 'file',
                    'skipOnEmpty' => true, 'extensions' => 'pdf, doc, docx, rar',
                    'maxFiles' => 3,
                    'maxSize' => 1024 * 1024 * 2],
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
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_practicas_personas' => 'Id Practicas Personas',
            'tipo' => 'Tipo',
            'id_detalle_practicas' => '',
            'codigo' => 'Práctica',
            'informe' => 'Informe',
            'fecha' => 'Fecha',
            'id_consultas_diagnosticos' => 'Diagnóstico'
        ];
    }
    
    
     /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    public function getDiagnostico()
    {
        return $this->hasOne(DiagnosticoConsulta::className(), ['id' => 'id_consultas_diagnosticos']);
    }


     /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'codigo']);
        #return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'id_snomed_procedimiento']);
    }

    public function getAdjuntos()
    {
        return $this->hasMany(Adjunto::className(), ['parent_id' => 'id'])
                                    ->onCondition(['parent_class' => 'ConsultaPracticas']);
    }

    //Busca los detalles practicas por consulta
    public function getPracticasPorConsulta($id_cons)
    {
        $practicas_persona = self::findAll(['id_consulta' => $id_cons]);
        return $practicas_persona;
                
    }

    public function subirArchivosAdjuntos()
    {
        if(!file_exists('uploads')) {
            mkdir('uploads');
        }
        if(!file_exists('uploads/practicas')) {
            mkdir('uploads/practicas');
        }

        if ($this->validate()) {            
            // Creamos la carpeta que lleva el id de consulta como nombre
            if (!file_exists('uploads/practicas/' . $this->id_consulta.'/')) {
                mkdir('uploads/practicas/' . $this->id_consulta, 0755);
            }
            
            foreach ($this->archivos_adjuntos as $file) {
                $file->saveAs('uploads/practicas/' . $this->id_consulta.'/'.$file->baseName . '.' . $file->extension);
            }
            return true;
        } else {            
            return false;
        }
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
