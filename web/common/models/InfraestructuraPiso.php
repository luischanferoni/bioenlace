<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "infraestructura_piso".
 *
 * @property int $id
 * @property int $nro_piso
 * @property string|null $descripcion
 * @property int $id_efector
 *
 * @property InfraestructuraSala[] $infraestructuraSalas
 */
class InfraestructuraPiso extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'infraestructura_piso';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [[ 'nro_piso', 'id_efector'], 'required'],
            [['id', 'nro_piso', 'id_efector'], 'integer'],
            [['descripcion'], 'string', 'max' => 255],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nro_piso' => 'Nro Piso',
            'descripcion' => 'Descripcion',
            'id_efector' => 'Id Efector',
        ];
    }

    /**
     * Gets query for [[InfraestructuraSalas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInfraestructuraSalas()
    {
        return $this->hasMany(InfraestructuraSala::className(), ['id_piso' => 'id']);
    }

    public function pisosPorEfector($id_efector)
    {
        return InfraestructuraPiso::find()->where('id_efector = ' . (int) $id_efector)->all();
    }

    /**
     * Internados (camas ocupadas con datos de internación) del efector.
     * @param int $id_efector
     * @return array lista de ['id', 'id_persona', 'nombre', 'cama', 'sala', 'piso']
     */
    public static function getInternadosPorEfector($id_efector)
    {
        $pisos = (new self())->pisosPorEfector($id_efector);
        $internados = [];
        foreach ($pisos as $piso) {
            foreach ($piso->infraestructuraSalas as $sala) {
                foreach ($sala->infraestructuraCamas as $cama) {
                    if ($cama->estado !== 'ocupada') {
                        continue;
                    }
                    $int = $cama->internacionActual;
                    if (!$int || !is_object($int)) {
                        continue;
                    }
                    $id = $int->id;
                    $internados[$id] = [
                        'id' => $id,
                        'id_persona' => $int->id_persona,
                        'nombre' => $int->paciente ? $int->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin nombre',
                        'cama' => $cama->nro_cama,
                        'sala' => $sala->nro_sala,
                        'piso' => $piso->nro_piso,
                    ];
                }
            }
        }
        return array_values($internados);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }
}
