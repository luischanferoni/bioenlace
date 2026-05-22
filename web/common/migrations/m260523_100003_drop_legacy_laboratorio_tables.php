<?php

use yii\db\Migration;

/**
 * Retiro LIS legacy (fase 5): tablas import CSV / NBU.
 *
 * Plan: web/docs/laboratorio/ — ingesta vía diagnostic_report.
 */
class m260523_100003_drop_legacy_laboratorio_tables extends Migration
{
    private const TABLES = [
        '{{%laboratorio_virus_respiratorios}}',
        '{{%laboratorio_dengue}}',
        '{{%laboratorio_nbu_snomed}}',
        '{{%laboratorio_nbu}}',
        '{{%laboratorio}}',
    ];

    public function safeUp()
    {
        foreach (self::TABLES as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }

    public function safeDown()
    {
        echo "m260523_100003: no se recrean tablas legacy de laboratorio.\n";
    }
}
