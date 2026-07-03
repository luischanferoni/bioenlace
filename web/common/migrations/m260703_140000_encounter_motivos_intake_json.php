<?php

use yii\db\Migration;

/**
 * Respuestas estructuradas previas al chat de motivos (encounter journey).
 */
class m260703_140000_encounter_motivos_intake_json extends Migration
{
    public function safeUp(): void
    {
        $table = '{{%encounter}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        if ($this->db->schema->getTableSchema($table, true)->getColumn('motivos_intake_json') !== null) {
            return;
        }
        $this->addColumn($table, 'motivos_intake_json', $this->text()->null()->after('reason_text'));
    }

    public function safeDown(): void
    {
        $table = '{{%encounter}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        if ($this->db->schema->getTableSchema($table, true)->getColumn('motivos_intake_json') === null) {
            return;
        }
        $this->dropColumn($table, 'motivos_intake_json');
    }
}
