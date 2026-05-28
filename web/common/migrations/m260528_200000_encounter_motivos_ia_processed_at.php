<?php

use yii\db\Migration;

/**
 * Marca cuándo se procesó el lote de motivos de consulta (IA única pre-atención).
 */
class m260528_200000_encounter_motivos_ia_processed_at extends Migration
{
    public function safeUp()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && !isset($schema->columns['motivos_ia_processed_at'])) {
            $this->addColumn($table, 'motivos_ia_processed_at', $this->dateTime()->null()->after('reason_text'));
        }
    }

    public function safeDown()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['motivos_ia_processed_at'])) {
            $this->dropColumn($table, 'motivos_ia_processed_at');
        }
    }
}
