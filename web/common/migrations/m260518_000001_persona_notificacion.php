<?php

use yii\db\Migration;

/**
 * Bandeja de alertas/notificaciones in-app (complementa push FCM).
 */
class m260518_000001_persona_notificacion extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%persona_notificacion}}', [
            'id' => $this->primaryKey(),
            'id_persona' => $this->integer()->notNull(),
            'tipo' => $this->string(64)->notNull(),
            'titulo' => $this->string(255)->notNull(),
            'cuerpo' => $this->text()->notNull(),
            'data_json' => $this->json()->null(),
            'leida_at' => $this->timestamp()->null(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('ix_persona_notificacion_persona', '{{%persona_notificacion}}', ['id_persona', 'created_at']);
        $this->createIndex('ix_persona_notificacion_leida', '{{%persona_notificacion}}', ['id_persona', 'leida_at']);

        $personas = $this->db->schema->getTableSchema('{{%personas}}', true);
        if ($personas !== null && isset($personas->columns['id_persona'])) {
            $this->addForeignKey(
                'fk_persona_notificacion_persona',
                '{{%persona_notificacion}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%persona_notificacion}}');
    }
}
