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
     * Internados (camas ocupadas) del efector, ordenados para recorrido de ronda (piso → sala → cama).
     *
     * @param int $id_efector
     * @return list<array<string, mixed>>
     */
    public static function getInternadosPorEfector($id_efector)
    {
        $pisos = (new self())->pisosPorEfector($id_efector);
        $internados = [];
        foreach ($pisos as $piso) {
            $pisoNro = (int) ($piso->nro_piso ?? 0);
            $pisoDesc = trim((string) ($piso->descripcion ?? ''));
            $pisoLabel = $pisoDesc !== '' ? $pisoDesc : ('Piso ' . $pisoNro);

            foreach ($piso->infraestructuraSalas as $sala) {
                $salaNro = (int) ($sala->nro_sala ?? 0);
                $salaDesc = trim((string) ($sala->descripcion ?? ''));
                $salaLabel = $salaDesc !== '' ? $salaDesc : ('Sala ' . $salaNro);

                foreach ($sala->infraestructuraCamas as $cama) {
                    if ($cama->estado !== 'ocupada') {
                        continue;
                    }
                    $int = $cama->internacionActual;
                    if (!$int || !is_object($int)) {
                        continue;
                    }
                    $id = (int) $int->id;
                    $paciente = $int->paciente ?? null;
                    $internados[$id] = [
                        'id' => $id,
                        'id_persona' => (int) $int->id_persona,
                        'nombre' => $paciente
                            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
                            : 'Sin nombre',
                        'documento' => $paciente ? (string) ($paciente->documento ?? '') : '',
                        'cama' => (string) ($cama->nro_cama ?? ''),
                        'sala' => $salaLabel,
                        'piso' => $pisoLabel,
                        'id_piso' => (int) $piso->id,
                        'id_sala' => (int) $sala->id,
                        'nro_piso' => $pisoNro,
                        'nro_sala' => $salaNro,
                        'nro_cama' => (int) ($cama->nro_cama ?? 0),
                    ];
                }
            }
        }

        $list = array_values($internados);
        usort($list, static function (array $a, array $b): int {
            return [$a['nro_piso'], $a['nro_sala'], $a['nro_cama'], $a['nombre']]
                <=> [$b['nro_piso'], $b['nro_sala'], $b['nro_cama'], $b['nombre']];
        });

        return $list;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }
}
