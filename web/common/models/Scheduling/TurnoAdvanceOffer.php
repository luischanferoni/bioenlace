<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Oferta individual de adelantamiento dentro de una campaña.
 *
 * @property int $id
 * @property int $id_campaign
 * @property int $sequence
 * @property int $id_turno_candidate
 * @property int $subject_persona_id
 * @property string $offer_token
 * @property string $estado
 * @property string|null $notification_ref
 * @property string $offered_at
 * @property string $expires_at
 * @property string|null $decided_at
 * @property string|null $result_detail
 * @property string $created_at
 */
class TurnoAdvanceOffer extends ActiveRecord
{
    public const ESTADO_PENDING = 'PENDING';
    public const ESTADO_EXPIRED = 'EXPIRED';
    public const ESTADO_ACCEPTED = 'ACCEPTED';
    public const ESTADO_SKIPPED = 'SKIPPED';
    public const ESTADO_UNAVAILABLE = 'UNAVAILABLE';

    public static function tableName()
    {
        return '{{%turno_advance_offer}}';
    }

    /**
     * @return list<string>
     */
    public static function estadoValues(): array
    {
        return [
            self::ESTADO_PENDING,
            self::ESTADO_EXPIRED,
            self::ESTADO_ACCEPTED,
            self::ESTADO_SKIPPED,
            self::ESTADO_UNAVAILABLE,
        ];
    }

    public function rules()
    {
        return [
            [[
                'id_campaign',
                'sequence',
                'id_turno_candidate',
                'subject_persona_id',
                'offer_token',
                'estado',
                'offered_at',
                'expires_at',
                'created_at',
            ], 'required'],
            [['id_campaign', 'sequence', 'id_turno_candidate', 'subject_persona_id'], 'integer'],
            [['offered_at', 'expires_at', 'decided_at', 'created_at'], 'safe'],
            [['offer_token', 'notification_ref'], 'string', 'max' => 64],
            [['estado'], 'string', 'max' => 16],
            [['result_detail'], 'string', 'max' => 128],
            [['estado'], 'in', 'range' => self::estadoValues()],
        ];
    }
}
