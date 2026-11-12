<?php

use yii\db\Migration;

/**
 * Estados y pricing de mensajes salientes de WhatsApp (webhook de Meta).
 */
class m261112_140000_whatsapp_message_status extends Migration
{
    private const TABLE = '{{%asistente_whatsapp_mensaje_estado}}';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'wamid' => $this->string(128)->notNull(),
            'wa_id' => $this->string(64)->notNull(),
            'status' => $this->string(24)->notNull(),
            'pricing_category' => $this->string(32)->null(),
            'pricing_type' => $this->string(48)->null(),
            'billable' => $this->tinyInteger(1)->null(),
            'conversation_id' => $this->string(128)->null(),
            'event_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('uq_wa_estado_wamid_status', self::TABLE, ['wamid', 'status'], true);
        $this->createIndex('idx_wa_estado_wa_created', self::TABLE, ['wa_id', 'created_at']);
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }
}
