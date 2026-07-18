<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Interacción append-only de una notificación push (entrega / apertura).
 *
 * @property int $id
 * @property int $id_persona_notificacion
 * @property int $id_persona
 * @property string $interaction_type
 * @property string $client_event_id
 * @property string|null $source
 * @property string|null $provider_message_id
 * @property string $occurred_at
 * @property string|null $meta_json
 * @property string $created_at
 */
class PersonaNotificacionInteraccion extends ActiveRecord
{
    public const TYPE_DELIVERED = 'DELIVERED';
    public const TYPE_OPENED = 'OPENED';

    public static function tableName()
    {
        return '{{%persona_notificacion_interaccion}}';
    }

    public function rules()
    {
        return [
            [['id_persona_notificacion', 'id_persona', 'interaction_type', 'client_event_id', 'occurred_at'], 'required'],
            [['id_persona_notificacion', 'id_persona'], 'integer'],
            [['interaction_type'], 'in', 'range' => [self::TYPE_DELIVERED, self::TYPE_OPENED]],
            [['client_event_id'], 'string', 'max' => 64],
            [['source'], 'string', 'max' => 64],
            [['provider_message_id'], 'string', 'max' => 191],
            [['meta_json', 'occurred_at', 'created_at'], 'safe'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return [self::TYPE_DELIVERED, self::TYPE_OPENED];
    }
}
