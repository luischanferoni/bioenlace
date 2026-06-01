<?php

use yii\db\Migration;

/**
 * Sugerencias IA (diagnósticos / prácticas) generadas con el lote de motivos de consulta.
 */
class m260606_100000_encounter_motivos_ia_insights_json extends Migration
{
    public function safeUp()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && !isset($schema->columns['motivos_ia_insights_json'])) {
            $this->addColumn(
                $table,
                'motivos_ia_insights_json',
                $this->text()->null()->after('motivos_ia_processed_at')
            );
        }
    }

    public function safeDown()
    {
        $table = '{{%encounter}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['motivos_ia_insights_json'])) {
            $this->dropColumn($table, 'motivos_ia_insights_json');
        }
    }
}
