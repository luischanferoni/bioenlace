<?php

namespace common\models;

use common\models\Clinical\Encounter;
use common\traits\EncounterIdLegacyConsultaColumnTrait;

/**
 * Antecedentes familiares — misma tabla `personas_antecedentes`.
 *
 * @property int|null $encounter_id
 * @property int|null $id_consulta Alias deprecated de {@see $encounter_id}.
 * @property-read Encounter|null $encounter
 */
class PersonasAntecedenteFamiliar extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    use EncounterIdLegacyConsultaColumnTrait;

    public $select2_codigo;

    public static function tableName()
    {
        return 'personas_antecedentes';
    }

    public function rules()
    {
        return [
            [['encounter_id'], 'required'],
            [['encounter_id', 'id_antecedente', 'id_persona'], 'integer'],
            [['encounter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['encounter_id' => 'id']],
            [['tipo_antecedente', 'origen_id_antecedente', 'codigo'], 'string'],
            ['select2_codigo', 'each', 'rule' => ['string']],
            [['id_antecedente'], 'default', 'value' => 0],
            [['codigo'], 'unique',
                'targetAttribute' => ['tipo_antecedente', 'codigo', 'id_persona', 'deleted_at'],
                'message' => 'El antecedente {value} ya se encuentra cargado para el paciente'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'encounter_id' => 'Encounter',
            'id_consulta' => 'Encounter (legacy)',
            'id_antecedente' => '',
            'id_snomed_situacion' => '',
            'select2_codigo' => 'Antecedentes Familiares',
        ];
    }

    public function getAntecedente()
    {
        return $this->hasOne(Antecedente::className(), ['id_antecedente' => 'id_antecedente']);
    }

    public function getSnomedSituacion()
    {
        return $this->hasOne(snomed\SnomedSituacion::className(), ['conceptId' => 'codigo']);
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return static[]
     */
    public static function getPersonasAntecedenteFamiliarPorEncounter(int $encounterId): array
    {
        return static::find()
            ->where(['tipo_antecedente' => 'Familiar'])
            ->andWhere(static::encounterIdQueryCondition($encounterId))
            ->andWhere(['deleted_at' => null])
            ->all();
    }

    /**
     * @deprecated use {@see getPersonasAntecedenteFamiliarPorEncounter()}
     * @return static[]
     */
    public static function getPersonasAntecedenteFamiliarPorConsulta($id_cons)
    {
        return static::getPersonasAntecedenteFamiliarPorEncounter((int) $id_cons);
    }

    public static function hardDeleteGrupoPorEncounter(int $encounterId, array $ids): void
    {
        if ($ids === [] || $encounterId <= 0) {
            return;
        }
        static::hardDeleteAll([
            'AND',
            ['in', 'id', $ids],
            static::encounterIdQueryCondition($encounterId),
        ]);
    }

    /**
     * @deprecated use {@see hardDeleteGrupoPorEncounter()}
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        static::hardDeleteGrupoPorEncounter((int) $id_consulta, $ids);
    }
}
