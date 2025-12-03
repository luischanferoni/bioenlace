<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedProcedimientos;

/**
 * This is the model class for table "seg_nivel_internacion_practica".
 *
 * @property int $id
 * @property string|null $conceptId
 * @property string|null $resultado
 * @property string|null $informe
 * @property string|null $fileName
 * @property int|null $id_rrhh_solicita
 * @property int|null $id_rrhh_realiza
 * @property int|null $id_internacion
 *
 * @property SegNivelInternacion $internacion
 */
class SegNivelInternacionPractica extends \yii\db\ActiveRecord
{
    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_practica';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_internacion','conceptId'], 'required'],
            [['id', 'id_rrhh_solicita', 'id_rrhh_realiza', 'id_internacion'], 'integer'],
            [['informe', 'fileName'], 'string'],
            [['imageFile'], 'file',
              'skipOnEmpty' => true,
              'uploadRequired' => 'No has seleccionado ningún archivo', //Mensaje de error
              //'maxSize' => 1024 * 1024 * 50, //Tamaño máximo del archivo ->1 MB 
              //'tooBig' => 'El tamaño máximo permitido es 5MB', //Mensaje de error
              //'minSize' => 1000, //Tamaño máximo del archivo ->10 Bytes
              //'tooSmall' => 'El tamaño mínimo permitido son 1 MB', //Mensaje de error
              'extensions' => 'pdf,png,jpg',  //Tipo de extensiones permitidas separadas por ,
              'wrongExtension' => 'El archivo {file} no contiene una extensión permitida ({extensions})', //Mensaje de error
              'maxFiles' => 1,   //N° de archivos permitidos para subir
              'tooMany' => 'El máximo de archivos permitidos son {limit}', //Mensaje de error
            ],
            [['conceptId'], 'string', 'max' => 45],
            [['resultado'], 'string', 'max' => 255],
            [['id'], 'unique'],
            [['id_internacion'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacion::className(), 'targetAttribute' => ['id_internacion' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'conceptId' => 'Concepto',
            'resultado' => 'Resultado',
            'informe' => 'Informe',
            'id_rrhh_solicita' => 'Solicitada por',
            'id_rrhh_realiza' => 'Realiza por',
            'id_internacion' => 'Internacion',
            'imageFile' => 'Adjuntar resultado',
        ];
    }

    /**
     * Gets query for [[Internacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacion()
    {
        return $this->hasOne(SegNivelInternacion::className(), ['id' => 'id_internacion']);
    }
    /**
     * Gets query for [[SnomedProcedimiento]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPracticaSnomed()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'conceptId']);
    }

    /**
     * Gets query for [[RrhhSolicita]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhSolicita()
    {
        return $this->hasOne(Rrhh_efector::className(), ['id_rr_hh' => 'id_rrhh_solicita']);
    }

    /**
     * Gets query for [[RrhhRealiza]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhRealiza()
    {
        return $this->hasOne(Rrhh_efector::className(), ['id_rr_hh' => 'id_rrhh_realiza']);
    }       
}
