<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Hueco liberado por cancelación — tabla `turno_waitlist_slot_offer`.
 */
class TurnoWaitlistSlotOffer extends ActiveRecord
{
    public const ESTADO_PENDING = 'PENDING';
    public const ESTADO_FILLED = 'FILLED';
    public const ESTADO_EXHAUSTED = 'EXHAUSTED';

    public static function tableName(): string
    {
        return 'turno_waitlist_slot_offer';
    }

    /**
     * @return list<string>
     */
    public static function estadoValues(): array
    {
        return [
            self::ESTADO_PENDING,
            self::ESTADO_FILLED,
            self::ESTADO_EXHAUSTED,
        ];
    }

    public function rules(): array
    {
        return [
            [
                ['id_efector', 'id_servicio', 'id_profesional_efector_servicio', 'fecha', 'hora', 'slot_json', 'estado', 'created_at'],
                'required',
            ],
            [['id_cancelled_turno', 'id_efector', 'id_servicio', 'id_profesional_efector_servicio'], 'integer'],
            [['fecha', 'hora', 'created_at', 'updated_at'], 'safe'],
            [['slot_json'], 'string'],
            [['estado'], 'in', 'range' => self::estadoValues()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeSlot(): array
    {
        $data = json_decode((string) $this->slot_json, true);

        return is_array($data) ? $data : [];
    }
}
