<?php

use yii\db\Migration;

/**
 * Tabla para mensajes de la conversación de motivos de consulta (paciente escribe/envía audio/fotos
 * antes de la consulta). Un proceso posterior codifica, corrige y estructura el contenido.
 *
 * Si safeUp() falla en cualquier paso, se ejecuta rollback (drop FK y tabla) para que
 * la próxima ejecución de migrate no encuentre "table already exists".
 */
class m250306_000001_consulta_motivos_messages extends Migration
{
    private $tableName = '{{%consulta_motivos_messages}}';

    public function safeUp()
    {
        try {
            $this->createTable($this->tableName, [
                'id' => $this->primaryKey(),
                'consulta_id' => $this->integer()->unsigned()->notNull()->comment('ID de la consulta'),
                'user_id' => $this->integer()->unsigned()->notNull()->comment('Usuario (paciente) que envía'),
                'user_name' => $this->string(100)->notNull(),
                'content' => $this->text()->notNull()->comment('Texto o ruta relativa del archivo'),
                'message_type' => $this->string(20)->notNull()->defaultValue('texto')->comment('texto, imagen, audio'),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_consulta_motivos_messages_consulta_id', $this->tableName, 'consulta_id');
            $this->addForeignKey(
                'fk_consulta_motivos_messages_consulta',
                $this->tableName,
                'consulta_id',
                '{{%consultas}}',
                'id_consulta',
                'CASCADE',
                'CASCADE'
            );
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
        // No se agrega FK a user: el tipo de user.id puede variar y provoca errno 150.
    }

    /**
     * Revierte los cambios de safeUp(). Se llama ante cualquier fallo para que la próxima
     * ejecución de migrate no encuentre la tabla ya creada.
     */
    private function rollback()
    {
        try {
            $this->dropForeignKey('fk_consulta_motivos_messages_consulta', $this->tableName);
        } catch (\Throwable $e) {
            // La FK puede no existir si falló antes de crearla
        }
        try {
            $this->dropTable($this->tableName);
        } catch (\Throwable $e) {
            // La tabla puede no existir
        }
    }

    public function safeDown()
    {
        $this->rollback();
    }
}
