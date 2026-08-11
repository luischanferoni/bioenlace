<?php

use yii\db\Migration;

/**
 * Quita columna residual de alias ConsultasConfiguracion.
 */
class m260811_160000_drop_encounter_definition_pasos_legacy extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%encounter_definition}}', true);
        if ($table !== null && isset($table->columns['pasos_legacy'])) {
            $this->dropColumn('{{%encounter_definition}}', 'pasos_legacy');
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%encounter_definition}}', true);
        if ($table !== null && !isset($table->columns['pasos_legacy'])) {
            $this->addColumn('{{%encounter_definition}}', 'pasos_legacy', $this->text()->null());
        }
    }
}
