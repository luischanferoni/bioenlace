<?php

use yii\db\Migration;

/**
 * Tablas para resumen con IA del historial del paciente (texto base y resumen por servicio).
 * Ver plan: web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md
 */
class m250305_000002_resumen_paciente_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        if ($this->db->schema->getTableSchema('{{%resumen_paciente_texto_base}}', true) === null) {
            $this->createTable('{{%resumen_paciente_texto_base}}', [
                'id_persona' => $this->integer()->notNull(),
                'texto_base' => $this->text()->comment('Texto estructurado del historial (datos codificados SNOMED)'),
                'ultima_actualizacion' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->addPrimaryKey('pk_resumen_paciente_texto_base', '{{%resumen_paciente_texto_base}}', 'id_persona');
            $this->addForeignKey(
                'fk_resumen_texto_base_persona',
                '{{%resumen_paciente_texto_base}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
        }

        if ($this->db->schema->getTableSchema('{{%resumen_paciente_servicio}}', true) === null) {
            $this->createTable('{{%resumen_paciente_servicio}}', [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->notNull(),
                'id_servicio' => $this->integer()->notNull()->comment('Servicio al que aplica el resumen'),
                'resumen' => $this->text()->comment('Resumen generado por IA para este servicio'),
                'ultima_actualizacion' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('idx_resumen_paciente_servicio_persona_servicio', '{{%resumen_paciente_servicio}}', ['id_persona', 'id_servicio'], true);
            $this->addForeignKey(
                'fk_resumen_servicio_persona',
                '{{%resumen_paciente_servicio}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_resumen_servicio_servicio',
                '{{%resumen_paciente_servicio}}',
                'id_servicio',
                '{{%servicios}}',
                'id_servicio',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%resumen_paciente_servicio}}', true) !== null) {
            $this->dropForeignKey('fk_resumen_servicio_servicio', '{{%resumen_paciente_servicio}}');
            $this->dropForeignKey('fk_resumen_servicio_persona', '{{%resumen_paciente_servicio}}');
            $this->dropTable('{{%resumen_paciente_servicio}}');
        }
        if ($this->db->schema->getTableSchema('{{%resumen_paciente_texto_base}}', true) !== null) {
            $this->dropForeignKey('fk_resumen_texto_base_persona', '{{%resumen_paciente_texto_base}}');
            $this->dropTable('{{%resumen_paciente_texto_base}}');
        }
    }
}
