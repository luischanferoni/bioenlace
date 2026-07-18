<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Generación inmutable del perfil factual de turnos de una persona.
 *
 * @property int $id
 * @property int $id_persona
 * @property int $profile_contract_version
 * @property int|null $source_watermark_event_id
 * @property string $as_of
 * @property string $completeness_status
 * @property string $generated_at
 * @property string|null $superseded_at
 * @property int|null $is_current
 */
class PersonaTurnosPerfil extends ActiveRecord
{
    public const COMPLETENESS_EMPTY = 'EMPTY';
    public const COMPLETENESS_PARTIAL = 'PARTIAL';
    public const COMPLETENESS_COMPLETE = 'COMPLETE';

    public static function tableName()
    {
        return '{{%persona_turnos_perfil}}';
    }

    /** @return list<string> */
    public static function completenessStatusValues(): array
    {
        return [
            self::COMPLETENESS_EMPTY,
            self::COMPLETENESS_PARTIAL,
            self::COMPLETENESS_COMPLETE,
        ];
    }

    public function rules()
    {
        return [
            [['id_persona', 'profile_contract_version', 'as_of', 'completeness_status', 'generated_at'], 'required'],
            [['id_persona', 'profile_contract_version', 'source_watermark_event_id', 'is_current'], 'integer'],
            [['as_of', 'generated_at', 'superseded_at'], 'safe'],
            [['completeness_status'], 'in', 'range' => self::completenessStatusValues()],
        ];
    }

    public function getMetricas()
    {
        return $this->hasMany(PersonaTurnosPerfilMetrica::class, ['id_perfil' => 'id']);
    }
}
