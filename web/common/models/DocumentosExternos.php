<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "documentos_externos".
 *
 * @property integer $id
 * @property string $titulo
 * @property string $tipo
 * @property integer $id_efector
 * @property integer $id_persona
 * @property integer $id_rrhh_servicio
 * @property integer $fecha
 * 
 */
class DocumentosExternos extends \yii\db\ActiveRecord
{
    const TIPO_ESTUDIO = 'ESTUDIO';
    const TIPO_HISTORIA_CLINICA = 'HISTORIA_CLINICA';
    const TIPO_CERTIFICADO = 'CERTIFICADO';

    const TIPOS = [
        self::TIPO_ESTUDIO => 'Estudio',
        self::TIPO_HISTORIA_CLINICA => 'Historia Clínica',
        self::TIPO_CERTIFICADO => 'Certificado'
    ];

    /**
     * @var UploadedFile[]
     */
    public $archivos_adjuntos;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'documentos_externos';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => Yii::$app->user->id,                
            ],
            'fechas' => [
                'class' => 'yii\behaviors\AttributesBehavior',
                'attributes' => [                    
                   'fecha' => [
                        \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => function ($event, $attribute) {  
                            if ($this->$attribute == "") {
                                return $this->$attribute;
                            }

                            if ($this->hasErrors($attribute)) {
                                return "";
                            }
                            $fecha = date_create_from_format('d/m/Y', $this->$attribute);                        
                            return date_format($fecha, 'Y-m-d');
                        }
                    ],
                ]
            ],            
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['titulo', 'tipo', 'id_efector', 'id_persona', 'id_rrhh_servicio', 'fecha', 'archivos_adjuntos'], 'required'],
            [['id_efector', 'id_persona', 'id_rrhh_servicio'], 'integer'],
            [['titulo', 'tipo'], 'string'],
            [['fecha'], 'date', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            [['archivos_adjuntos'], 'file',
                    'skipOnEmpty' => true, 'extensions' => 'pdf',
                    'maxFiles' => 3,
                    'maxSize' => 1024 * 1024 * 2],            
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'titulo' => 'Título',
            'tipo' => 'Tipo',
            'id_efector' => 'Efector',
            'id_persona' => 'Paciente',
            'id_rrhh_servicio' => 'Profesional',
            'fecha' => 'Fecha',
        ];
    }
}
