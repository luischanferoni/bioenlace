<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "alergias".
 *
 * @property int $id
 * @property string $tipo
 * @property string $categoria
 * @property string $criticidad
 * @property int $id_snomed_hallazgo
 * @property int $id_persona
 * @property string $fecha_creacion
 *
 * @property SnomedHallazgos $codigoSnomed
 * @property Persona $persona
 */
class Alergias extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const TIPO_ALERGIA = 'allergy';
    const TIPO_INTOLERANCIA = 'intolerance';

    const TIPOS = [
        self::TIPO_ALERGIA => 'Alergia', 
        self::TIPO_INTOLERANCIA => 'Intolerancia', 
    ];

    const CATEGORIA_FOOD = 'food';
    const CATEGORIA_MEDICATION = 'medication';
    const CATEGORIA_ENVIRONMENT = 'environment';
    const CATEGORIA_BIOLOGY = 'biology';

    const CATEGORIAS = [
        self::CATEGORIA_FOOD => 'Comida', 
        self::CATEGORIA_MEDICATION => 'Medicación',
        self::CATEGORIA_ENVIRONMENT => 'Ambiente',
        self::CATEGORIA_BIOLOGY => 'Biología',
    ];
    public $terminos_motivos;
    public $id_servicio;
    const CRITICIDAD_LOW = 'low';
    const CRITICIDAD_HIGH = 'high';
    const CRITICIDAD_UNABLE_TO_ASSES = 'unable-to-assess';

    const CRITICIDADES = [
        self::CRITICIDAD_LOW => 'Bajo', 
        self::CRITICIDAD_HIGH => 'Alto',
        self::CRITICIDAD_UNABLE_TO_ASSES => 'No se puede establecer',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_alergias';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_snomed_hallazgo'], 'required'],
            [['tipo', 'categoria', 'criticidad','terminos_motivos'], 'string'],
            [['id_persona','id_servicio'], 'integer'],
            [['fecha_creacion'], 'safe'],
            [['id_persona'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona' => 'id_persona']],
            [['id_snomed_hallazgo'], 'exist', 'skipOnError' => true, 'targetClass' => snomed\SnomedHallazgos::className(), 'targetAttribute' => ['id_snomed_hallazgo' => 'conceptId']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tipo' => 'Tipo',
            'categoria' => 'Categoria',
            'criticidad' => 'Criticidad',
            'id_snomed_hallazgo' => 'Descripción',
            'id_persona' => 'Id Persona',
            'fecha_creacion' => 'Fecha Creacion',
        ];
    }

    /**
     * Gets query for [[CodigoSnomed]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoSnomed()
    {
        return $this->hasOne(snomed\SnomedHallazgos::className(), ['conceptId' => 'id_snomed_hallazgo']);
    }

    /**
     * Gets query for [[Persona]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    public function getIdConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
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
