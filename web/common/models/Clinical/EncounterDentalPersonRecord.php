<?php

namespace common\models\Clinical;

use common\models\Person\Persona;

use Yii;

/**
 * ActiveRecord for the legacy dental encounter-person table.
 *
 * @property int $id_odonto_consulta
 * @property int $encounter_id
 * @property int $id_turno
 * @property int $id_persona
 * @property int $id_nomenclador_odonto
 * @property string $id_odonto_pieza
 * @property string $estado_pieza
 * @property string $observaciones
 */
class EncounterDentalPersonRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'odonto_consulta_persona';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['encounter_id', 'id_turno', 'id_persona', 'id_odonto_pieza', 'estado_pieza'], 'required'],
            [['encounter_id', 'id_turno', 'id_persona', 'id_nomenclador_odonto'], 'integer'],
            [['id_odonto_pieza', 'estado_pieza'], 'string', 'max' => 50],
            [['observaciones'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_odonto_consulta' => 'Id Odonto Consulta',
            'encounter_id' => 'Encounter',
            'id_turno' => 'Id Turno',
            'id_persona' => 'Id Persona',
            'id_nomenclador_odonto' => 'Id Nomenclador Odonto',
            'id_odonto_pieza' => 'Id Odonto Pieza',
            'estado_pieza' => 'Estado Pieza',
            'fecha_alta' => 'Fecha alta en la tabla',
            'fecha_completado' => 'Fecha tratamiento completado',
            'observaciones' => 'Observaciones',
        ];
    }

    public function findAllForPerson($personId)
    {
        return self::findAll(['id_persona' => $personId]);
    }
}
