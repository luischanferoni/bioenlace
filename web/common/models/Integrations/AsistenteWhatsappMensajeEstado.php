<?php

namespace common\models\Integrations;

use yii\db\ActiveRecord;

/**
 * Estado y pricing de un mensaje saliente de WhatsApp.
 *
 * Se alimenta del webhook de `statuses` de Meta. El pricing permite medir cuánto
 * cobra Meta por mensaje (`free_customer_service` vs `regular`).
 *
 * @property int $id
 * @property string $wamid
 * @property string $wa_id
 * @property string $status
 * @property string|null $pricing_category
 * @property string|null $pricing_type
 * @property int|null $billable
 * @property string|null $conversation_id
 * @property string|null $event_at
 * @property string $created_at
 */
class AsistenteWhatsappMensajeEstado extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%asistente_whatsapp_mensaje_estado}}';
    }

    public function rules(): array
    {
        return [
            [['wamid', 'wa_id', 'status'], 'required'],
            [['wamid'], 'string', 'max' => 128],
            [['wa_id'], 'string', 'max' => 64],
            [['status'], 'string', 'max' => 24],
            [['pricing_category'], 'string', 'max' => 32],
            [['pricing_type'], 'string', 'max' => 48],
            [['billable'], 'integer'],
            [['conversation_id'], 'string', 'max' => 128],
            [['event_at', 'created_at'], 'safe'],
        ];
    }

    /**
     * @param array{wamid: string, wa_id: string, status: string, pricing_category: ?string, pricing_type: ?string, billable: ?int, conversation_id: ?string, event_at: ?string} $data
     */
    public static function record(array $data): void
    {
        $wamid = trim((string) ($data['wamid'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));
        if ($wamid === '' || $status === '') {
            return;
        }

        if (self::find()->where(['wamid' => $wamid, 'status' => $status])->exists()) {
            return;
        }

        $row = new self([
            'wamid' => $wamid,
            'wa_id' => trim((string) ($data['wa_id'] ?? '')),
            'status' => $status,
            'pricing_category' => $data['pricing_category'] ?? null,
            'pricing_type' => $data['pricing_type'] ?? null,
            'billable' => $data['billable'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'event_at' => $data['event_at'] ?? null,
        ]);

        try {
            $row->save(false);
        } catch (\Throwable) {
            // El webhook de Meta no debe fallar por un estado repetido o una carrera.
        }
    }
}
