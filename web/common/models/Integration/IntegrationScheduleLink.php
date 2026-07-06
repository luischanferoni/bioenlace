<?php

namespace common\models\Integration;

use common\models\ProfesionalEfectorServicio;
use yii\db\ActiveRecord;

/**
 * Vínculo verificado Schedule HAPI → PES interno.
 *
 * @property int $id
 * @property string $source_system
 * @property string $external_schedule_id
 * @property int $id_profesional_efector_servicio
 * @property string $resolution_method
 * @property string|null $actor_fingerprint
 * @property string $status pending|verified|stale|revoked
 * @property string|null $verified_at
 * @property int|null $verified_by_user_id
 * @property string $created_at
 * @property string $updated_at
 */
class IntegrationScheduleLink extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_STALE = 'stale';
    public const STATUS_REVOKED = 'revoked';

    public const METHOD_MANUAL = 'manual';
    public const METHOD_COMPOSITE_V1 = 'composite_v1';

    public static function tableName(): string
    {
        return 'integration_schedule_link';
    }

    public function rules(): array
    {
        return [
            [['source_system', 'external_schedule_id', 'id_profesional_efector_servicio'], 'required'],
            [['id_profesional_efector_servicio', 'verified_by_user_id'], 'integer'],
            [['source_system'], 'string', 'max' => 64],
            [['external_schedule_id'], 'string', 'max' => 128],
            [['resolution_method'], 'string', 'max' => 32],
            [['actor_fingerprint'], 'string', 'max' => 64],
            [['status'], 'string', 'max' => 16],
            [['verified_at', 'created_at', 'updated_at'], 'safe'],
            [['resolution_method'], 'default', 'value' => self::METHOD_MANUAL],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
        ];
    }

    public function getPes()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    public static function findVerified(string $sourceSystem, string $externalScheduleId): ?self
    {
        /** @var self|null $row */
        $row = static::find()
            ->where([
                'source_system' => $sourceSystem,
                'external_schedule_id' => $externalScheduleId,
                'status' => self::STATUS_VERIFIED,
            ])
            ->one();

        return $row;
    }
}
