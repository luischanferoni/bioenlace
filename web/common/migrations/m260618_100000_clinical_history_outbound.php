<?php

use yii\db\Migration;

/**
 * Cola de export FHIR de historia clínica (interoperabilidad saliente).
 *
 * @see web/docs/plans/interoperabilidad-historia-clinica/
 */
class m260618_100000_clinical_history_outbound extends Migration
{
    public function safeUp()
    {
        $job = '{{%clinical_history_outbound_job}}';
        if ($this->db->schema->getTableSchema($job, true) === null) {
            $this->createTable($job, [
                'id' => $this->primaryKey(),
                'encounter_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'efector_id' => $this->integer()->null(),
                'exchange_profile' => $this->string(64)->notNull()->defaultValue('encounter-document-v1'),
                'connector_key' => $this->string(32)->notNull()->defaultValue('null'),
                'estado' => $this->string(20)->notNull()->defaultValue('PENDIENTE'),
                'run_at' => $this->dateTime()->notNull(),
                'bundle_hash' => $this->char(64)->null(),
                'bundle_json' => $this->text()->null()->comment('Snapshot Bundle FHIR al enviar'),
                'external_id' => $this->string(128)->null()->comment('Id en servidor nacional'),
                'ultimo_error' => $this->text()->null(),
                'intentos' => $this->integer()->notNull()->defaultValue(0),
                'sent_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_ch_out_encounter_profile', $job, ['encounter_id', 'exchange_profile']);
            $this->createIndex('ix_ch_out_estado_run', $job, ['estado', 'run_at']);
            $this->createIndex('ix_ch_out_subject', $job, ['subject_persona_id', 'created_at']);
            $this->addForeignKey(
                'fk_ch_out_encounter',
                $job,
                'encounter_id',
                '{{%encounter}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $audit = '{{%clinical_history_outbound_audit}}';
        if ($this->db->schema->getTableSchema($audit, true) === null) {
            $this->createTable($audit, [
                'id' => $this->primaryKey(),
                'job_id' => $this->integer()->notNull(),
                'event_type' => $this->string(32)->notNull(),
                'meta_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_ch_out_audit_job', $audit, ['job_id', 'created_at']);
            $this->addForeignKey(
                'fk_ch_out_audit_job',
                $audit,
                'job_id',
                $job,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $audit = '{{%clinical_history_outbound_audit}}';
        if ($this->db->schema->getTableSchema($audit, true) !== null) {
            $this->dropForeignKey('fk_ch_out_audit_job', $audit);
            $this->dropTable($audit);
        }

        $job = '{{%clinical_history_outbound_job}}';
        if ($this->db->schema->getTableSchema($job, true) !== null) {
            $this->dropForeignKey('fk_ch_out_encounter', $job);
            $this->dropTable($job);
        }
    }
}
