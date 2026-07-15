<?php

use yii\db\Migration;

/**
 * Snapshot de extracción IA entre /analizar y /guardar (compartido entre nodos vía BD).
 */
class m260715_170000_encounter_capture_analysis extends Migration
{
    private string $table = '{{%encounter_capture_analysis}}';

    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema !== null) {
            return;
        }

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'token' => $this->string(64)->notNull(),
            'subject_persona_id' => $this->integer()->null(),
            'parent_type' => $this->string(32)->null(),
            'parent_id' => $this->integer()->null(),
            'encounter_id' => $this->integer()->null(),
            'texto_hash' => $this->string(64)->notNull(),
            'datos_extraidos_json' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_encounter_capture_analysis_token', $this->table, 'token', true);
        $this->createIndex(
            'idx_encounter_capture_analysis_ctx',
            $this->table,
            ['subject_persona_id', 'parent_type', 'parent_id', 'texto_hash']
        );
        $this->createIndex('idx_encounter_capture_analysis_encounter', $this->table, 'encounter_id');
        $this->createIndex('idx_encounter_capture_analysis_created', $this->table, 'created_at');
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema($this->table, true);
        if ($schema === null) {
            return;
        }
        $this->dropTable($this->table);
    }
}
