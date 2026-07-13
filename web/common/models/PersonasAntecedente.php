<?php

namespace common\models;

use common\models\Clinical\Encounter;
use common\traits\EncounterIdLegacyConsultaColumnTrait;

/**
 * Antecedentes de persona — tabla `persona_antecedentes`.
 *
 * La columna `encounter_id` (legacy `id_consulta`) almacena el id de {@see Encounter}.
 *
 * @property int|null $encounter_id
 * @property int|null $id_consulta Alias deprecated de {@see $encounter_id}.
 * @property int|null $id_antecedente
 * @property int|null $id_snomed_situacion
 * @property string|null $deleted_at
 * @property string|null $deleted_by
 * @property-read Antecedente|null $antecedente
 * @property-read Persona|null $persona
 * @property-read Encounter|null $encounter
 */
class PersonasAntecedente extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    use EncounterIdLegacyConsultaColumnTrait;

    public $terminos_motivos;
    public $id_servicio;
    public $select2_codigo;

    public static function tableName()
    {
        return 'persona_antecedentes';
    }

    public function rules()
    {
        return [
            [['encounter_id'], 'required'],
            [['encounter_id', 'id_antecedente', 'id_persona', 'id_servicio'], 'integer'],
            [['encounter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['encounter_id' => 'id']],
            [['tipo_antecedente', 'origen_id_antecedente', 'codigo', 'terminos_motivos'], 'string'],
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
            'select2_codigo' => 'Antecedentes Personales',
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

    public function getParent()
    {
        return $this->hasOne($this->parent_class, ['id' => 'parent_id']);
    }

    /**
     * Antecedentes personales vinculados a un encounter.
     *
     * @return static[]
     */
    public static function getPersonasAntecedentePorEncounter(int $encounterId): array
    {
        return static::find()
            ->where(['tipo_antecedente' => 'Personal'])
            ->andWhere(static::encounterIdQueryCondition($encounterId))
            ->andWhere(['deleted_at' => null])
            ->all();
    }

    /**
     * @deprecated use {@see getPersonasAntecedentePorEncounter()}
     * @return static[]
     */
    public static function getPersonasAntecedentePorConsulta($id_cons)
    {
        return static::getPersonasAntecedentePorEncounter((int) $id_cons);
    }

    public static function getPersonasAntecedentePorSnomed($id_persona, $codigo_snomed, $tipo)
    {
        return static::find()
            ->where(['id_persona' => $id_persona])
            ->andWhere(['codigo' => $codigo_snomed])
            ->andWhere(['tipo_antecedente' => $tipo])
            ->all();
    }

    public function beforeSave($insert)
    {
        if ($this->isRelationPopulated('parent')) {
            $this->parent_class = get_class($this->parent);
        }

        return true;
    }

    /**
     * Hard delete mientras el encounter no esté cerrado (edición clínica).
     */
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
