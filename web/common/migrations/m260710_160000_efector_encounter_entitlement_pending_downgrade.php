<?php

use yii\db\Migration;

/**
 * Downgrade diferido de max_pes: pending_max_pes + pending_effective_on.
 */
class m260710_160000_efector_encounter_entitlement_pending_downgrade extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%efector_encounter_entitlement}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['pending_max_pes'])) {
            $this->addColumn(
                $table,
                'pending_max_pes',
                $this->integer()->unsigned()->null()->comment('Tope a aplicar en pending_effective_on')->after('max_pes')
            );
        }
        if (!isset($schema->columns['pending_effective_on'])) {
            $this->addColumn(
                $table,
                'pending_effective_on',
                $this->date()->null()->comment('Fecha (inclusive) en que aplica pending_max_pes')->after('pending_max_pes')
            );
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%efector_encounter_entitlement}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['pending_effective_on'])) {
            $this->dropColumn($table, 'pending_effective_on');
        }
        if (isset($schema->columns['pending_max_pes'])) {
            $this->dropColumn($table, 'pending_max_pes');
        }
    }
}
