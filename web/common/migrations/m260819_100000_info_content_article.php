<?php

use yii\db\Migration;

/**
 * Tabla de artículos informativos con alcance jerárquico (producto → provincia → efector).
 * Usada por el asistente para responder preguntas informativas del paciente/staff.
 */
class m260819_100000_info_content_article extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'topic' => $this->string(80)->notNull()->comment('Clave temática (ej. representacion, teleconsulta, turnos)'),
            'title' => $this->string(255)->notNull(),
            'body' => $this->text()->notNull()->comment('Contenido markdown'),
            'scope' => $this->string(20)->notNull()->defaultValue('producto')->comment('producto|provincia|efector'),
            'id_provincia' => $this->integer()->null(),
            'id_efector' => $this->integer()->null(),
            'keywords' => $this->string(500)->null()->comment('Palabras clave separadas por coma para clasificación'),
            'activo' => $this->boolean()->notNull()->defaultValue(true),
            'priority' => $this->smallInteger()->notNull()->defaultValue(0)->comment('Mayor = más prioritario dentro del mismo scope'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('NOW()'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('NOW()'),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
        ]);

        $this->createIndex('idx_info_content_topic_scope', self::TABLE, ['topic', 'scope', 'activo']);
        $this->createIndex('idx_info_content_efector', self::TABLE, ['id_efector']);
        $this->createIndex('idx_info_content_provincia', self::TABLE, ['id_provincia']);

        $this->addForeignKey(
            'fk_info_content_provincia',
            self::TABLE, 'id_provincia',
            '{{%geo_provincias}}', 'id_provincia',
            'SET NULL', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_info_content_efector',
            self::TABLE, 'id_efector',
            '{{%efectores}}', 'id_efector',
            'SET NULL', 'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable(self::TABLE);
    }
}
