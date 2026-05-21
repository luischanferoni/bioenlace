<?php

use yii\db\ColumnSchema;
use yii\db\Migration;
use yii\db\TableSchema;

/**
 * Fase 1 — Esquema FHIR-native (núcleo + órdenes + extensiones).
 *
 * Tabla Condition → `clinical_condition` (evita palabra reservada SQL).
 * Sin ETL: tablas legacy se eliminan en {@see m260520_100002_clinical_fhir_drop_legacy}.
 */
class m260520_100000_clinical_fhir_create_schema extends Migration
{
    public function safeUp()
    {
        $opts = $this->mysqlTableOptions();

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);

        $this->createEncounterDefinition($opts);
        $this->createEncounter($opts, $turnos);
        $this->createEpisodeOfCare($opts);
        $this->createCarePlan($opts);
        $this->createCarePlanActivity($opts);
        $this->createGoal($opts);
        $this->createClinicalCondition($opts);
        $this->createMedicationRequest($opts);
        $this->createServiceRequest($opts);
        $this->createProcedure($opts);
        $this->createDeviceRequest($opts);
        $this->createNutritionOrder($opts);
        $this->createMedicationAdministration($opts);
        $this->createObservation($opts);
        $this->createClinicalImpression($opts);
        $this->createAllergyIntolerance($opts);
        $this->createProcedureOdontologyExt($opts);
        $this->createVisionPrescription($opts);
    }

    public function safeDown()
    {
        $tables = [
            '{{%vision_prescription}}',
            '{{%procedure_odontology_ext}}',
            '{{%allergy_intolerance}}',
            '{{%clinical_impression}}',
            '{{%observation}}',
            '{{%medication_administration}}',
            '{{%nutrition_order}}',
            '{{%device_request}}',
            '{{%procedure}}',
            '{{%service_request}}',
            '{{%medication_request}}',
            '{{%clinical_condition}}',
            '{{%goal}}',
            '{{%care_plan_activity}}',
            '{{%care_plan}}',
            '{{%episode_of_care}}',
            '{{%encounter}}',
            '{{%encounter_definition}}',
        ];
        foreach ($tables as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }

    private function mysqlTableOptions(): ?string
    {
        if ($this->db->driverName === 'mysql') {
            return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        return null;
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

    private function createEncounterDefinition(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%encounter_definition}}', true) !== null) {
            return;
        }

        $this->createTable('{{%encounter_definition}}', array_merge([
            'id' => $this->primaryKey(),
            'service_id' => $this->integer()->unsigned()->notNull()->comment('servicios.id_servicio'),
            'encounter_class' => $this->string(10)->notNull()->comment('HL7 encounter-class'),
            'workflow_json' => $this->text()->notNull()->comment('Pasos del wizard (ex pasos_json)'),
            'pasos_legacy' => $this->text()->null()->comment('Texto legacy opcional'),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_encounter_def_service_class', '{{%encounter_definition}}', ['service_id', 'encounter_class'], true);
    }

    private function createEncounter(?string $opts, ?TableSchema $turnos): void
    {
        if ($this->db->schema->getTableSchema('{{%encounter}}', true) !== null) {
            return;
        }

        $appointmentCol = $this->columnDefMatchingTurnosPk($turnos)->null()->comment('turnos.id_turnos');

        $this->createTable('{{%encounter}}', array_merge([
            'id' => $this->primaryKey(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'appointment_id' => $appointmentCol,
            'encounter_class' => $this->string(10)->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('in-progress')->comment('FHIR EncounterStatus simplificado'),
            'period_start' => $this->dateTime()->null(),
            'period_end' => $this->dateTime()->null(),
            'service_id' => $this->integer()->unsigned()->null(),
            'efector_id' => $this->integer()->unsigned()->null(),
            'id_profesional_efector_servicio' => $this->integer()->unsigned()->null(),
            'parent_type' => $this->string(128)->null()->comment('Clase origen (ex parent_class)'),
            'parent_id' => $this->integer()->unsigned()->null(),
            'workflow_step' => $this->integer()->null()->comment('Paso actual del wizard'),
            'reason_text' => $this->text()->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_encounter_subject_status', '{{%encounter}}', ['subject_persona_id', 'status']);
        $this->createIndex('idx_encounter_appointment', '{{%encounter}}', 'appointment_id');
        $this->createIndex('idx_encounter_parent', '{{%encounter}}', ['parent_type', 'parent_id']);

        $this->addForeignKey(
            'fk_encounter_subject_persona',
            '{{%encounter}}',
            'subject_persona_id',
            '{{%personas}}',
            'id_persona',
            'RESTRICT',
            'RESTRICT'
        );
        if ($this->db->schema->getTableSchema('{{%turnos}}', true) !== null) {
            $this->addForeignKey(
                'fk_encounter_appointment',
                '{{%encounter}}',
                'appointment_id',
                '{{%turnos}}',
                'id_turnos',
                'SET NULL',
                'RESTRICT'
            );
        }
    }

    private function createEpisodeOfCare(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%episode_of_care}}', true) !== null) {
            return;
        }

        $this->createTable('{{%episode_of_care}}', array_merge([
            'id' => $this->primaryKey(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'type_code' => $this->string(64)->notNull()->comment('inpatient, chronic, program, …'),
            'period_start' => $this->dateTime()->null(),
            'period_end' => $this->dateTime()->null(),
            'efector_id' => $this->integer()->unsigned()->null(),
            'internacion_id' => $this->integer()->null()->comment('seg_nivel_internacion.id si aplica'),
            'title' => $this->string(255)->null(),
            'description' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_episode_subject_status', '{{%episode_of_care}}', ['subject_persona_id', 'status']);
        $this->addForeignKey(
            'fk_episode_subject_persona',
            '{{%episode_of_care}}',
            'subject_persona_id',
            '{{%personas}}',
            'id_persona',
            'RESTRICT',
            'RESTRICT'
        );
    }

    private function createCarePlan(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%care_plan}}', true) !== null) {
            return;
        }

        $this->createTable('{{%care_plan}}', array_merge([
            'id' => $this->primaryKey(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('draft'),
            'intent' => $this->string(16)->notNull()->defaultValue('plan'),
            'category' => $this->string(64)->notNull()->comment('Ver CARE_PLAN_CATEGORIES.md'),
            'period_start' => $this->dateTime()->null(),
            'period_end' => $this->dateTime()->null(),
            'encounter_id' => $this->integer()->unsigned()->null(),
            'episode_of_care_id' => $this->integer()->unsigned()->null(),
            'title' => $this->string(255)->null(),
            'description' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_care_plan_subject_status', '{{%care_plan}}', ['subject_persona_id', 'status']);
        $this->createIndex('idx_care_plan_encounter', '{{%care_plan}}', 'encounter_id');
        $this->addForeignKey('fk_care_plan_subject', '{{%care_plan}}', 'subject_persona_id', '{{%personas}}', 'id_persona', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_care_plan_encounter', '{{%care_plan}}', 'encounter_id', '{{%encounter}}', 'id', 'SET NULL', 'RESTRICT');
        $this->addForeignKey('fk_care_plan_episode', '{{%care_plan}}', 'episode_of_care_id', '{{%episode_of_care}}', 'id', 'SET NULL', 'RESTRICT');
    }

    private function createCarePlanActivity(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%care_plan_activity}}', true) !== null) {
            return;
        }

        $this->createTable('{{%care_plan_activity}}', array_merge([
            'id' => $this->primaryKey(),
            'care_plan_id' => $this->integer()->unsigned()->notNull(),
            'kind' => $this->string(64)->notNull()->comment('medication-request, service-request, …'),
            'resource_type' => $this->string(64)->notNull(),
            'resource_id' => $this->integer()->unsigned()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'status' => $this->string(32)->notNull()->defaultValue('not-started'),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_cpa_plan', '{{%care_plan_activity}}', 'care_plan_id');
        $this->addForeignKey('fk_cpa_care_plan', '{{%care_plan_activity}}', 'care_plan_id', '{{%care_plan}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createGoal(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%goal}}', true) !== null) {
            return;
        }

        $this->createTable('{{%goal}}', array_merge([
            'id' => $this->primaryKey(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'care_plan_id' => $this->integer()->unsigned()->null(),
            'encounter_id' => $this->integer()->unsigned()->null(),
            'lifecycle_status' => $this->string(32)->notNull()->defaultValue('active'),
            'description' => $this->text()->notNull(),
            'target_json' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_goal_subject', '{{%goal}}', 'subject_persona_id', '{{%personas}}', 'id_persona', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk_goal_care_plan', '{{%goal}}', 'care_plan_id', '{{%care_plan}}', 'id', 'SET NULL', 'RESTRICT');
        $this->addForeignKey('fk_goal_encounter', '{{%goal}}', 'encounter_id', '{{%encounter}}', 'id', 'SET NULL', 'RESTRICT');
    }

    private function createClinicalCondition(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%clinical_condition}}', true) !== null) {
            return;
        }

        $this->createTable('{{%clinical_condition}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'code' => $this->string(32)->notNull(),
            'code_system' => $this->string(64)->notNull()->defaultValue('http://hl7.org/fhir/sid/icd-10'),
            'display' => $this->string(512)->null(),
            'clinical_status' => $this->string(32)->notNull(),
            'verification_status' => $this->string(32)->notNull(),
            'diagnosis_role' => $this->string(32)->null()->comment('principal, secondary, …'),
            'recorded_date' => $this->dateTime()->null(),
            'onset_datetime' => $this->dateTime()->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_clinical_condition_encounter', '{{%clinical_condition}}', 'encounter_id');
        $this->addForeignKey('fk_clinical_condition_encounter', '{{%clinical_condition}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_clinical_condition_subject', '{{%clinical_condition}}', 'subject_persona_id', '{{%personas}}', 'id_persona', 'RESTRICT', 'RESTRICT');
    }

    private function createMedicationRequest(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%medication_request}}', true) !== null) {
            return;
        }

        $this->createTable('{{%medication_request}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'care_plan_id' => $this->integer()->unsigned()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'intent' => $this->string(16)->notNull()->defaultValue('order'),
            'medication_code' => $this->string(64)->null(),
            'medication_display' => $this->string(512)->null(),
            'dosage_text' => $this->text()->null(),
            'dosage_json' => $this->text()->null(),
            'authored_on' => $this->dateTime()->null(),
            'id_profesional_efector_servicio' => $this->integer()->unsigned()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_medication_request_encounter', '{{%medication_request}}', 'encounter_id');
        $this->addForeignKey('fk_medication_request_encounter', '{{%medication_request}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createServiceRequest(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%service_request}}', true) !== null) {
            return;
        }

        $this->createTable('{{%service_request}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'care_plan_id' => $this->integer()->unsigned()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'intent' => $this->string(16)->notNull()->defaultValue('order'),
            'category' => $this->string(64)->notNull()->comment('laboratory, imaging, referral, …'),
            'code' => $this->string(64)->null(),
            'code_system' => $this->string(64)->null(),
            'display' => $this->string(512)->null(),
            'occurrence_datetime' => $this->dateTime()->null(),
            'note' => $this->text()->null(),
            'id_profesional_efector_servicio' => $this->integer()->unsigned()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_service_request_encounter', '{{%service_request}}', 'encounter_id');
        $this->addForeignKey('fk_service_request_encounter', '{{%service_request}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createProcedure(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%procedure}}', true) !== null) {
            return;
        }

        $this->createTable('{{%procedure}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'service_request_id' => $this->integer()->unsigned()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('in-progress'),
            'code' => $this->string(64)->null(),
            'code_system' => $this->string(64)->null(),
            'display' => $this->string(512)->null(),
            'performed_datetime' => $this->dateTime()->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_procedure_encounter', '{{%procedure}}', 'encounter_id');
        $this->addForeignKey('fk_procedure_encounter', '{{%procedure}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_procedure_service_request', '{{%procedure}}', 'service_request_id', '{{%service_request}}', 'id', 'SET NULL', 'RESTRICT');
    }

    private function createDeviceRequest(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%device_request}}', true) !== null) {
            return;
        }

        $this->createTable('{{%device_request}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'care_plan_id' => $this->integer()->unsigned()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'code' => $this->string(64)->null(),
            'display' => $this->string(512)->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_device_request_encounter', '{{%device_request}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createNutritionOrder(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%nutrition_order}}', true) !== null) {
            return;
        }

        $this->createTable('{{%nutrition_order}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'oral_diet_json' => $this->text()->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_nutrition_order_encounter', '{{%nutrition_order}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createMedicationAdministration(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%medication_administration}}', true) !== null) {
            return;
        }

        $this->createTable('{{%medication_administration}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'medication_request_id' => $this->integer()->unsigned()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('completed'),
            'effective_datetime' => $this->dateTime()->null(),
            'dosage_json' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_med_admin_encounter', '{{%medication_administration}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createObservation(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%observation}}', true) !== null) {
            return;
        }

        $this->createTable('{{%observation}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('final'),
            'category' => $this->string(64)->notNull(),
            'code' => $this->string(64)->notNull(),
            'code_system' => $this->string(64)->null(),
            'value_quantity' => $this->decimal(12, 4)->null(),
            'value_unit' => $this->string(32)->null(),
            'value_string' => $this->text()->null(),
            'value_json' => $this->text()->null(),
            'effective_datetime' => $this->dateTime()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_observation_encounter', '{{%observation}}', 'encounter_id');
        $this->addForeignKey('fk_observation_encounter', '{{%observation}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createClinicalImpression(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%clinical_impression}}', true) !== null) {
            return;
        }

        $this->createTable('{{%clinical_impression}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('completed'),
            'summary' => $this->text()->notNull(),
            'finding_json' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_clinical_impression_encounter', '{{%clinical_impression}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    private function createAllergyIntolerance(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%allergy_intolerance}}', true) !== null) {
            return;
        }

        $this->createTable('{{%allergy_intolerance}}', array_merge([
            'id' => $this->primaryKey(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'encounter_id' => $this->integer()->unsigned()->null(),
            'clinical_status' => $this->string(32)->notNull(),
            'verification_status' => $this->string(32)->notNull(),
            'type' => $this->string(32)->null(),
            'category' => $this->string(32)->null(),
            'code' => $this->string(64)->null(),
            'display' => $this->string(512)->null(),
            'criticality' => $this->string(16)->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->createIndex('idx_allergy_subject', '{{%allergy_intolerance}}', 'subject_persona_id');
        $this->addForeignKey('fk_allergy_subject', '{{%allergy_intolerance}}', 'subject_persona_id', '{{%personas}}', 'id_persona', 'RESTRICT', 'RESTRICT');
    }

    private function createProcedureOdontologyExt(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%procedure_odontology_ext}}', true) !== null) {
            return;
        }

        $this->createTable('{{%procedure_odontology_ext}}', [
            'procedure_id' => $this->integer()->unsigned()->notNull(),
            'tooth_number' => $this->string(8)->null(),
            'surfaces' => $this->string(32)->null(),
            'time_qualifier' => $this->string(16)->null()->comment('PASADA|PRESENTE|FUTURA'),
        ], $opts);

        $this->addPrimaryKey('pk_procedure_odontology_ext', '{{%procedure_odontology_ext}}', 'procedure_id');
        $this->addForeignKey('fk_procedure_odonto_procedure', '{{%procedure_odontology_ext}}', 'procedure_id', '{{%procedure}}', 'id', 'CASCADE', 'CASCADE');
    }

    private function createVisionPrescription(?string $opts): void
    {
        if ($this->db->schema->getTableSchema('{{%vision_prescription}}', true) !== null) {
            return;
        }

        $this->createTable('{{%vision_prescription}}', array_merge([
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->unsigned()->notNull(),
            'subject_persona_id' => $this->integer()->unsigned()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'lens_spec_json' => $this->text()->null(),
            'note' => $this->text()->null(),
        ], $this->auditColumns()), $opts);

        $this->addForeignKey('fk_vision_prescription_encounter', '{{%vision_prescription}}', 'encounter_id', '{{%encounter}}', 'id', 'CASCADE', 'RESTRICT');
    }

    /**
     * FK a turnos.id_turnos: mismo tipo que la PK legacy.
     *
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function columnDefMatchingTurnosPk(?TableSchema $turnos)
    {
        if ($turnos === null || !isset($turnos->columns['id_turnos'])) {
            return $this->integer()->unsigned();
        }

        return $this->columnDefFromSchemaColumn($turnos->columns['id_turnos']);
    }

    /**
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function columnDefFromSchemaColumn(ColumnSchema $col)
    {
        switch ($col->type) {
            case 'bigint':
                $def = $this->bigInteger();
                break;
            case 'smallint':
                $def = $this->smallInteger();
                break;
            default:
                $def = $this->integer();
        }
        if ($col->unsigned) {
            $def->unsigned();
        }

        return $def;
    }
}
