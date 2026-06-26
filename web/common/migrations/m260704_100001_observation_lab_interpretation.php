<?php

use yii\db\Migration;

/**
 * Interpretación y rango de referencia en observation (laboratorio FHIR).
 */
class m260704_100001_observation_lab_interpretation extends Migration
{
    public function safeUp()
    {
        $table = '{{%observation}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['interpretation_code'])) {
            $this->addColumn($table, 'interpretation_code', $this->string(16)->null()->after('value_unit'));
        }
        if (!isset($schema->columns['reference_range_low'])) {
            $this->addColumn($table, 'reference_range_low', $this->decimal(12, 4)->null()->after('interpretation_code'));
        }
        if (!isset($schema->columns['reference_range_high'])) {
            $this->addColumn($table, 'reference_range_high', $this->decimal(12, 4)->null()->after('reference_range_low'));
        }
    }

    public function safeDown()
    {
        $table = '{{%observation}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        foreach (['reference_range_high', 'reference_range_low', 'interpretation_code'] as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($table, $col);
            }
        }
    }
}
