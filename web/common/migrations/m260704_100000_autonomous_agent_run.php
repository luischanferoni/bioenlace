<?php

use yii\db\Migration;

/**
 * Auditoría de pasos decisorios de agentes autónomos.
 */
class m260704_100000_autonomous_agent_run extends Migration
{
    public function safeUp()
    {
        $table = '{{%agent_run}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'agent_id' => $this->string(64)->notNull(),
            'trigger_type' => $this->string(48)->notNull(),
            'trigger_id' => $this->integer()->null(),
            'encounter_id' => $this->integer()->null(),
            'subject_persona_id' => $this->integer()->null(),
            'rule_id' => $this->string(64)->null(),
            'outcome' => $this->string(48)->notNull(),
            'facts_json' => $this->text()->null(),
            'decision_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_agent_run_agent_created', $table, ['agent_id', 'created_at']);
        $this->createIndex('ix_agent_run_trigger', $table, ['trigger_type', 'trigger_id']);
        $this->createIndex('ix_agent_run_encounter', $table, ['encounter_id']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%agent_run}}');
    }
}
