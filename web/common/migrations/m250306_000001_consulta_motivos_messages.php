<?php

use yii\db\Migration;

/**
 * Tabla para mensajes de la conversación de motivos de consulta (paciente escribe/envía audio/fotos
 * antes de la consulta). Un proceso posterior codifica, corrige y estructura el contenido.
 */
class m250306_000001_consulta_motivos_messages extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%consulta_motivos_messages}}', [
            'id' => $this->primaryKey(),
            'consulta_id' => $this->integer()->notNull()->comment('ID de la consulta'),
            'user_id' => $this->integer()->notNull()->comment('Usuario (paciente) que envía'),
            'user_name' => $this->string(100)->notNull(),
            'content' => $this->text()->notNull()->comment('Texto o ruta relativa del archivo'),
            'message_type' => $this->string(20)->notNull()->defaultValue('texto')->comment('texto, imagen, audio'),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_consulta_motivos_messages_consulta_id', '{{%consulta_motivos_messages}}', 'consulta_id');
        $this->addForeignKey(
            'fk_consulta_motivos_messages_consulta',
            '{{%consulta_motivos_messages}}',
            'consulta_id',
            '{{%consultas}}',
            'id_consulta',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_consulta_motivos_messages_user',
            '{{%consulta_motivos_messages}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_consulta_motivos_messages_user', '{{%consulta_motivos_messages}}');
        $this->dropForeignKey('fk_consulta_motivos_messages_consulta', '{{%consulta_motivos_messages}}');
        $this->dropTable('{{%consulta_motivos_messages}}');
    }
}
