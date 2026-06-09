<?php

use yii\db\Migration;

/**
 * Packs de cohorte (asistencia, seguimiento, educación) y cola de generación IA/batch.
 */
class m260613_100000_care_cohort_packs extends Migration
{
    public function safeUp()
    {
        $pack = '{{%care_cohort_pack}}';
        if ($this->db->schema->getTableSchema($pack, true) === null) {
            $this->createTable($pack, [
                'id' => $this->primaryKey(),
                'pack_type' => $this->string(32)->notNull(),
                'cohort_key' => $this->char(64)->notNull(),
                'cohort_profile_json' => $this->text()->null(),
                'content_json' => $this->text()->notNull(),
                'ia_context' => $this->string(64)->notNull(),
                'source' => $this->string(24)->notNull()->defaultValue('sync'),
                'generated_at' => $this->dateTime()->notNull(),
                'expires_at' => $this->dateTime()->notNull(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_care_cohort_pack_type_key', $pack, ['pack_type', 'cohort_key'], true);
            $this->createIndex('ix_care_cohort_pack_expires', $pack, ['pack_type', 'expires_at']);
        }

        $job = '{{%care_pack_job}}';
        if ($this->db->schema->getTableSchema($job, true) === null) {
            $this->createTable($job, [
                'id' => $this->primaryKey(),
                'pack_type' => $this->string(32)->notNull(),
                'cohort_key' => $this->char(64)->notNull(),
                'cohort_profile_json' => $this->text()->null(),
                'encounter_id' => $this->integer()->null(),
                'subject_persona_id' => $this->integer()->null(),
                'status' => $this->string(20)->notNull()->defaultValue('pending'),
                'mode' => $this->string(20)->notNull()->defaultValue('sync'),
                'run_at' => $this->dateTime()->notNull(),
                'attempts' => $this->integer()->notNull()->defaultValue(0),
                'pack_id' => $this->integer()->null(),
                'vertex_batch_job_name' => $this->string(255)->null(),
                'vertex_batch_custom_id' => $this->string(64)->null(),
                'prompt_snapshot' => $this->text()->null(),
                'last_error' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_care_pack_job_status_run', $job, ['status', 'run_at']);
            $this->createIndex('ix_care_pack_job_vertex', $job, ['vertex_batch_job_name', 'status']);
            $this->createIndex('ix_care_pack_job_cohort', $job, ['pack_type', 'cohort_key', 'status']);
        }

        $binding = '{{%care_encounter_pack}}';
        if ($this->db->schema->getTableSchema($binding, true) === null) {
            $this->createTable($binding, [
                'encounter_id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'cohort_key' => $this->char(64)->notNull(),
                'cohort_profile_json' => $this->text()->null(),
                'assistance_pack_id' => $this->integer()->null(),
                'followup_pack_id' => $this->integer()->null(),
                'education_pack_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_care_encounter_pack_persona', $binding, 'subject_persona_id');
            $this->addForeignKey(
                'fk_care_encounter_pack_encounter',
                $binding,
                'encounter_id',
                '{{%encounter}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $binding = '{{%care_encounter_pack}}';
        if ($this->db->schema->getTableSchema($binding, true) !== null) {
            $this->dropForeignKey('fk_care_encounter_pack_encounter', $binding);
            $this->dropTable($binding);
        }
        $job = '{{%care_pack_job}}';
        if ($this->db->schema->getTableSchema($job, true) !== null) {
            $this->dropTable($job);
        }
        $pack = '{{%care_cohort_pack}}';
        if ($this->db->schema->getTableSchema($pack, true) !== null) {
            $this->dropTable($pack);
        }
    }
}
