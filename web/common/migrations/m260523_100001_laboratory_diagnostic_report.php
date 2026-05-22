<?php

use yii\db\Migration;

/**
 * Laboratorio externo FHIR: diagnostic_report + trazabilidad en observation.
 *
 * Plan: web/docs/plans/laboratorio-external-fhir/phases/01-foundation-db.md
 */
class m260523_100001_laboratory_diagnostic_report extends Migration
{
    public function safeUp()
    {
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        if ($this->db->schema->getTableSchema('{{%diagnostic_report}}', true) === null) {
            $this->createTable('{{%diagnostic_report}}', array_merge([
                'id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'encounter_id' => $this->integer()->null(),
                'source_system' => $this->string(64)->notNull()->comment('Clave conector: sianlabs, …'),
                'external_id' => $this->string(128)->notNull()->comment('Id FHIR del DiagnosticReport'),
                'status' => $this->string(32)->notNull()->defaultValue('final'),
                'code' => $this->string(64)->null(),
                'code_system' => $this->string(128)->null(),
                'display' => $this->string(512)->null(),
                'issued_at' => $this->dateTime()->null(),
                'conclusion' => $this->text()->null(),
                'payload_json' => $this->text()->null()->comment('Recurso FHIR serializado'),
            ], $this->auditColumns()), $opts);

            $this->createIndex(
                'idx_diagnostic_report_subject_issued',
                '{{%diagnostic_report}}',
                ['subject_persona_id', 'issued_at']
            );
            $this->createIndex(
                'uidx_diagnostic_report_source_external',
                '{{%diagnostic_report}}',
                ['source_system', 'external_id'],
                true
            );
            $this->addForeignKey(
                'fk_diagnostic_report_subject',
                '{{%diagnostic_report}}',
                'subject_persona_id',
                '{{%personas}}',
                'id_persona',
                'RESTRICT',
                'RESTRICT'
            );
            if ($this->db->schema->getTableSchema('{{%encounter}}', true) !== null) {
                $this->addForeignKey(
                    'fk_diagnostic_report_encounter',
                    '{{%diagnostic_report}}',
                    'encounter_id',
                    '{{%encounter}}',
                    'id',
                    'SET NULL',
                    'RESTRICT'
                );
            }
        }

        $obsSchema = $this->db->schema->getTableSchema('{{%observation}}', true);
        if ($obsSchema !== null) {
            if (!isset($obsSchema->columns['source_system'])) {
                $this->addColumn('{{%observation}}', 'source_system', $this->string(64)->null()->after('category'));
            }
            if (!isset($obsSchema->columns['external_id'])) {
                $this->addColumn('{{%observation}}', 'external_id', $this->string(128)->null()->after('source_system'));
            }
            if (!isset($obsSchema->columns['diagnostic_report_id'])) {
                $this->addColumn('{{%observation}}', 'diagnostic_report_id', $this->integer()->null()->after('encounter_id'));
            }
            $obsSchema = $this->db->schema->getTableSchema('{{%observation}}', true);
            if ($obsSchema->columns['encounter_id']->allowNull === false) {
                $this->alterColumn('{{%observation}}', 'encounter_id', $this->integer()->null());
            }
            if ($this->db->schema->getTableSchema('{{%diagnostic_report}}', true) !== null) {
                $this->addForeignKey(
                    'fk_observation_diagnostic_report',
                    '{{%observation}}',
                    'diagnostic_report_id',
                    '{{%diagnostic_report}}',
                    'id',
                    'CASCADE',
                    'RESTRICT'
                );
            }
            $this->createIndex(
                'uidx_observation_source_external',
                '{{%observation}}',
                ['source_system', 'external_id'],
                true
            );
        }
    }

    public function safeDown()
    {
        $obsSchema = $this->db->schema->getTableSchema('{{%observation}}', true);
        if ($obsSchema !== null) {
            if ($this->db->schema->getTableSchema('{{%observation}}', true)->foreignKeys) {
                try {
                    $this->dropForeignKey('fk_observation_diagnostic_report', '{{%observation}}');
                } catch (\Throwable $e) {
                }
            }
            try {
                $this->dropIndex('uidx_observation_source_external', '{{%observation}}');
            } catch (\Throwable $e) {
            }
            foreach (['diagnostic_report_id', 'external_id', 'source_system'] as $col) {
                if (isset($obsSchema->columns[$col])) {
                    $this->dropColumn('{{%observation}}', $col);
                }
            }
        }

        if ($this->db->schema->getTableSchema('{{%diagnostic_report}}', true) !== null) {
            $this->dropTable('{{%diagnostic_report}}');
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
