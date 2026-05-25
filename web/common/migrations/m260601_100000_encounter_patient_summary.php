<?php

use yii\db\Migration;

/**
 * Resumen de atención publicado al paciente (texto IA + snapshot).
 */
class m260601_100000_encounter_patient_summary extends Migration
{
    public function safeUp()
    {
        $summary = '{{%encounter_patient_summary}}';
        if ($this->db->schema->getTableSchema($summary, true) === null) {
            $this->createTable($summary, [
                'id' => $this->primaryKey(),
                'encounter_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'narrative_text' => $this->text()->null(),
                'summary_json' => $this->text()->null(),
                'published_at' => $this->dateTime()->notNull(),
                'version' => $this->integer()->notNull()->defaultValue(1),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_encounter_patient_summary_encounter', $summary, 'encounter_id', true);
            $this->createIndex('ix_encounter_patient_summary_persona', $summary, ['subject_persona_id', 'published_at']);
            $this->addForeignKey(
                'fk_encounter_patient_summary_encounter',
                $summary,
                'encounter_id',
                '{{%encounter}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $queue = '{{%encounter_patient_summary_publish_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) === null) {
            $this->createTable($queue, [
                'id' => $this->primaryKey(),
                'encounter_id' => $this->integer()->notNull(),
                'run_at' => $this->dateTime()->notNull(),
                'estado' => $this->string(20)->notNull()->defaultValue('PENDIENTE'),
                'intentos' => $this->integer()->notNull()->defaultValue(0),
                'ultimo_error' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_eps_publish_queue_encounter', $queue, 'encounter_id', true);
            $this->createIndex('ix_eps_publish_queue_run', $queue, ['estado', 'run_at']);
        }
    }

    public function safeDown()
    {
        $queue = '{{%encounter_patient_summary_publish_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) !== null) {
            $this->dropTable($queue);
        }
        $summary = '{{%encounter_patient_summary}}';
        if ($this->db->schema->getTableSchema($summary, true) !== null) {
            $this->dropTable($summary);
        }
    }
}
