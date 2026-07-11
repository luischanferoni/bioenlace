<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Idempotencia de mensajes entrantes WhatsApp (wamid).
 *
 * @property int $id
 * @property string $wamid
 * @property string $wa_id
 * @property string $processed_at
 */
class AsistenteWhatsappMensaje extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%asistente_whatsapp_mensaje}}';
    }

    public function rules(): array
    {
        return [
            [['wamid', 'wa_id'], 'required'],
            [['wamid'], 'string', 'max' => 128],
            [['wa_id'], 'string', 'max' => 64],
            [['wamid'], 'unique'],
            [['processed_at'], 'safe'],
        ];
    }

    /**
     * @return bool true si es la primera vez (se insertó); false si ya existía
     */
    public static function claim(string $wamid, string $waId): bool
    {
        $wamid = trim($wamid);
        $waId = trim($waId);
        if ($wamid === '' || $waId === '') {
            return false;
        }
        if (self::find()->where(['wamid' => $wamid])->exists()) {
            return false;
        }

        $row = new self([
            'wamid' => $wamid,
            'wa_id' => $waId,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            return $row->save(false);
        } catch (\Throwable) {
            return false;
        }
    }
}
