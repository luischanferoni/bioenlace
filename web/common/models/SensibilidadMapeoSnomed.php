<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Mapeo de código SNOMED (de una de las tablas snomed_*) a categoría de sensibilidad.
 *
 * @property int $id
 * @property string $tabla_snomed
 * @property string $codigo conceptId SNOMED
 * @property int $id_categoria
 * @property SensibilidadCategoria $categoria
 */
class SensibilidadMapeoSnomed extends ActiveRecord
{
    /** Tablas SNOMED permitidas (clave => etiqueta) */
    const TABLAS = [
        'snomed_hallazgos' => 'Hallazgos',
        'snomed_medicamentos' => 'Medicamentos',
        'snomed_motivos_consulta' => 'Motivos consulta',
        'snomed_problemas' => 'Problemas',
        'snomed_procedimientos' => 'Procedimientos',
        'snomed_sintomas' => 'Síntomas',
        'snomed_situacion' => 'Situación',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sensibilidad_mapeo_snomed';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tabla_snomed', 'codigo', 'id_categoria'], 'required'],
            [['id_categoria'], 'integer'],
            [['tabla_snomed'], 'in', 'range' => array_keys(self::TABLAS)],
            [['codigo'], 'string', 'max' => 50],
            [['tabla_snomed', 'codigo'], 'unique', 'targetAttribute' => ['tabla_snomed', 'codigo']],
            [['id_categoria'], 'exist', 'skipOnError' => true, 'targetClass' => SensibilidadCategoria::class, 'targetAttribute' => ['id_categoria' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tabla_snomed' => 'Tabla SNOMED',
            'codigo' => 'Código (conceptId)',
            'id_categoria' => 'Categoría sensibilidad',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategoria()
    {
        return $this->hasOne(SensibilidadCategoria::class, ['id' => 'id_categoria']);
    }

    /**
     * Obtener el término (term) del código desde la tabla SNOMED correspondiente.
     * @return string
     */
    public function getTerminoSnomed()
    {
        if (!isset(self::TABLAS[$this->tabla_snomed])) {
            return $this->codigo;
        }
        $tableName = $this->tabla_snomed;
        $row = \Yii::$app->db->createCommand(
            'SELECT term FROM ' . $tableName . ' WHERE conceptId = :codigo',
            [':codigo' => $this->codigo]
        )->queryOne();
        return ($row && isset($row['term'])) ? $row['term'] : $this->codigo;
    }
}
