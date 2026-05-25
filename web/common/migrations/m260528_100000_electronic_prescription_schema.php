<?php

use yii\db\Migration;

/**
 * Receta electrónica — documento emitido (modo A, MVP Fase 1).
 *
 * Receta electrónica: ver web/docs/producto/receta-electronica.md
 */
class m260528_100000_electronic_prescription_schema extends Migration
{
    public function safeUp()
    {
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        if ($this->db->schema->getTableSchema('{{%electronic_prescription}}', true) === null) {
            $this->createTable('{{%electronic_prescription}}', array_merge([
                'id' => $this->primaryKey(),
                'encounter_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->null(),
                'status' => $this->string(32)->notNull()->defaultValue('draft'),
                'prescription_number' => $this->string(64)->null()->comment('Único al emitir'),
                'diagnosis_code' => $this->string(64)->null(),
                'diagnosis_code_system' => $this->string(128)->null(),
                'diagnosis_display' => $this->string(512)->null(),
                'valid_from' => $this->date()->null(),
                'valid_until' => $this->date()->null(),
                'issued_at' => $this->dateTime()->null(),
                'cancelled_at' => $this->dateTime()->null(),
                'cancellation_reason' => $this->text()->null(),
                'fhir_bundle_json' => $this->text()->null()->comment('Snapshot Bundle MSAL RDI'),
                'notes' => $this->text()->null(),
            ], $this->auditColumns()), $opts);

            $this->createIndex('idx_ep_encounter', '{{%electronic_prescription}}', 'encounter_id');
            $this->createIndex('idx_ep_subject_status', '{{%electronic_prescription}}', ['subject_persona_id', 'status']);
            $this->createIndex('uidx_ep_prescription_number', '{{%electronic_prescription}}', 'prescription_number', true);

            $this->addForeignKey(
                'fk_ep_encounter',
                '{{%electronic_prescription}}',
                'encounter_id',
                '{{%encounter}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
            $this->addForeignKey(
                'fk_ep_subject',
                '{{%electronic_prescription}}',
                'subject_persona_id',
                '{{%personas}}',
                'id_persona',
                'RESTRICT',
                'RESTRICT'
            );
        }

        if ($this->db->schema->getTableSchema('{{%electronic_prescription_item}}', true) === null) {
            $this->createTable('{{%electronic_prescription_item}}', array_merge([
                'id' => $this->primaryKey(),
                'electronic_prescription_id' => $this->integer()->notNull(),
                'line_number' => $this->smallInteger()->notNull()->defaultValue(1),
                'medication_request_id' => $this->integer()->null(),
                'medication_code' => $this->string(64)->null(),
                'medication_code_system' => $this->string(128)->null()->defaultValue('http://snomed.info/sct'),
                'medication_display' => $this->string(512)->null(),
                'quantity_text' => $this->string(128)->null(),
                'dosage_text' => $this->text()->null(),
            ], $this->auditColumns()), $opts);

            $this->createIndex('idx_epi_prescription', '{{%electronic_prescription_item}}', 'electronic_prescription_id');
            $this->addForeignKey(
                'fk_epi_prescription',
                '{{%electronic_prescription_item}}',
                'electronic_prescription_id',
                '{{%electronic_prescription}}',
                'id',
                'CASCADE',
                'RESTRICT'
            );
            if ($this->db->schema->getTableSchema('{{%medication_request}}', true) !== null) {
                $this->addForeignKey(
                    'fk_epi_medication_request',
                    '{{%electronic_prescription_item}}',
                    'medication_request_id',
                    '{{%medication_request}}',
                    'id',
                    'SET NULL',
                    'RESTRICT'
                );
            }
        }

        if ($this->db->schema->getTableSchema('{{%electronic_prescription_event}}', true) === null) {
            $this->createTable('{{%electronic_prescription_event}}', [
                'id' => $this->primaryKey(),
                'electronic_prescription_id' => $this->integer()->notNull(),
                'event_type' => $this->string(32)->notNull(),
                'actor_user_id' => $this->integer()->unsigned()->null(),
                'payload_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $opts);

            $this->createIndex('idx_epe_prescription', '{{%electronic_prescription_event}}', 'electronic_prescription_id');
            $this->addForeignKey(
                'fk_epe_prescription',
                '{{%electronic_prescription_event}}',
                'electronic_prescription_id',
                '{{%electronic_prescription}}',
                'id',
                'CASCADE',
                'RESTRICT'
            );
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%electronic_prescription_event}}', true) !== null) {
            $this->dropTable('{{%electronic_prescription_event}}');
        }
        if ($this->db->schema->getTableSchema('{{%electronic_prescription_item}}', true) !== null) {
            $this->dropTable('{{%electronic_prescription_item}}');
        }
        if ($this->db->schema->getTableSchema('{{%electronic_prescription}}', true) !== null) {
            $this->dropTable('{{%electronic_prescription}}');
        }
    }

    /** @return array<string, mixed> */
    private function auditColumns(): array
    {
        return [
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->unsigned()->null(),
            'updated_by' => $this->integer()->unsigned()->null(),
            'deleted_by' => $this->integer()->unsigned()->null(),
        ];
    }
}
