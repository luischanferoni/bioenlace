<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\AsistenteWhatsappVinculo;
use yii\db\Migration;

/**
 * Vínculo WhatsApp ↔ usuario paciente + idempotencia de mensajes entrantes.
 */
class m260711_150000_asistente_whatsapp_vinculo extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $vinculo = '{{%asistente_whatsapp_vinculo}}';
        if ($this->db->schema->getTableSchema($vinculo, true) === null) {
            $this->createTable($vinculo, [
                'id' => $this->primaryKey()->unsigned(),
                'wa_id' => $this->string(64)->notNull(),
                'user_id' => $this->integer()->null(),
                'id_persona' => $this->integer()->null(),
                'estado' => MigrationEnumColumn::mysqlEnum(
                    AsistenteWhatsappVinculo::estadoValues(),
                    AsistenteWhatsappVinculo::ESTADO_PENDIENTE_CONFIRMACION,
                    true,
                    'PENDIENTE_CONFIRMACION|ACTIVO|RECHAZADO'
                ),
                'pending_user_id' => $this->integer()->null(),
                'pending_id_persona' => $this->integer()->null(),
                'flow_session' => $this->text()->null()->comment('JSON intent_id/subintent_id/draft'),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex('uq_asistente_whatsapp_vinculo_wa_id', $vinculo, ['wa_id'], true);
            $this->createIndex('idx_asistente_whatsapp_vinculo_user', $vinculo, ['user_id', 'estado']);
        }

        $mensaje = '{{%asistente_whatsapp_mensaje}}';
        if ($this->db->schema->getTableSchema($mensaje, true) === null) {
            $this->createTable($mensaje, [
                'id' => $this->primaryKey()->unsigned(),
                'wamid' => $this->string(128)->notNull(),
                'wa_id' => $this->string(64)->notNull(),
                'processed_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ]);
            $this->createIndex('uq_asistente_whatsapp_mensaje_wamid', $mensaje, ['wamid'], true);
            $this->createIndex('idx_asistente_whatsapp_mensaje_wa', $mensaje, ['wa_id']);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        foreach (['{{%asistente_whatsapp_mensaje}}', '{{%asistente_whatsapp_vinculo}}'] as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }
}
