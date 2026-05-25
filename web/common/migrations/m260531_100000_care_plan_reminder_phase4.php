<?php

use yii\db\Migration;

/**
 * Fase 4 recordatorios care plan: reminder_json en service_request + preferencias servidor.
 */
class m260531_100000_care_plan_reminder_phase4 extends Migration
{
    public function safeUp()
    {
        $sr = '{{%service_request}}';
        if ($this->db->schema->getTableSchema($sr, true) !== null) {
            $schema = $this->db->schema->getTableSchema($sr, true);
            if (!isset($schema->columns['reminder_json'])) {
                $this->addColumn(
                    $sr,
                    'reminder_json',
                    $this->text()->null()->comment('timing para recordatorios paciente (misma forma que dosage_json)')
                );
            }
        }

        $pref = '{{%persona_care_plan_reminder_pref}}';
        if ($this->db->schema->getTableSchema($pref, true) === null) {
            $this->createTable($pref, [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->notNull(),
                'care_plan_id' => $this->integer()->null()->comment('null = global'),
                'activity_id' => $this->integer()->null()->comment('null = plan o global'),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'custom_times_json' => $this->text()->null(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(
                'uidx_pcp_reminder_pref_scope',
                $pref,
                ['id_persona', 'care_plan_id', 'activity_id'],
                true
            );
            $this->addForeignKey(
                'fk_pcp_reminder_pref_persona',
                $pref,
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $pref = '{{%persona_care_plan_reminder_pref}}';
        if ($this->db->schema->getTableSchema($pref, true) !== null) {
            $this->dropTable($pref);
        }

        $sr = '{{%service_request}}';
        if ($this->db->schema->getTableSchema($sr, true) !== null) {
            $schema = $this->db->schema->getTableSchema($sr, true);
            if (isset($schema->columns['reminder_json'])) {
                $this->dropColumn($sr, 'reminder_json');
            }
        }
    }
}
