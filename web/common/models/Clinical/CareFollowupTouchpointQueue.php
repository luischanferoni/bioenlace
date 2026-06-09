<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property string $touchpoint_key
 * @property string $run_at
 * @property string $estado
 * @property string $title
 * @property string $purpose
 * @property string $form_kind
 * @property string|null $education_refs
 * @property int|null $followup_pack_id
 * @property int|null $education_pack_id
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property string|null $notified_at
 * @property string $created_at
 * @property string $updated_at
 */
class CareFollowupTouchpointQueue extends ActiveRecord
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_NOTIFICADA = 'NOTIFICADA';
    public const ESTADO_COMPLETADA = 'COMPLETADA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_FALLIDA = 'FALLIDA';

    public static function tableName(): string
    {
        return '{{%care_followup_touchpoint_queue}}';
    }

    /**
     * @return list<string>
     */
    public function getEducationRefsArray(): array
    {
        if ($this->education_refs === null || $this->education_refs === '') {
            return [];
        }
        $decoded = json_decode($this->education_refs, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
