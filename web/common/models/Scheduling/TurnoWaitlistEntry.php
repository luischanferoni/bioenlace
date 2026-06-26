<?php

namespace common\models\Scheduling;

use common\models\Person\Persona;
use yii\db\ActiveRecord;

/**
 * Inscripción a lista de espera por servicio/efector — tabla `turno_waitlist_entry`.
 */
class TurnoWaitlistEntry extends ActiveRecord
{
    public const ESTADO_ACTIVE = 'ACTIVE';
    public const ESTADO_OFFERED = 'OFFERED';
    public const ESTADO_FULFILLED = 'FULFILLED';
    public const ESTADO_EXPIRED = 'EXPIRED';
    public const ESTADO_CANCELLED = 'CANCELLED';

    public static function tableName(): string
    {
        return 'turno_waitlist_entry';
    }

    /**
     * @return list<string>
     */
    public static function estadoValues(): array
    {
        return [
            self::ESTADO_ACTIVE,
            self::ESTADO_OFFERED,
            self::ESTADO_FULFILLED,
            self::ESTADO_EXPIRED,
            self::ESTADO_CANCELLED,
        ];
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'id_efector', 'id_servicio', 'estado', 'enrolled_at', 'created_at'], 'required'],
            [
                ['subject_persona_id', 'id_efector', 'id_servicio', 'id_profesional_efector_servicio', 'slot_offer_id', 'id_turno_fulfilled'],
                'integer',
            ],
            [['enrolled_at', 'offer_expires_at', 'created_at', 'updated_at'], 'safe'],
            [['estado'], 'in', 'range' => self::estadoValues()],
            [['urgency_band'], 'string', 'max' => 8],
            [['offer_token'], 'string', 'max' => 64],
        ];
    }

    public function getSubject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }

    public function getSlotOffer(): \yii\db\ActiveQuery
    {
        return $this->hasOne(TurnoWaitlistSlotOffer::class, ['id' => 'slot_offer_id']);
    }
}
