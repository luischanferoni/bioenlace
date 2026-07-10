<?php

use yii\db\Migration;

/**
 * Contrato comercial por efector: encounter_class habilitadas + tope de PES.
 */
class m260710_140000_efector_encounter_entitlement extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%efector_encounter_entitlement}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'id_efector' => $this->integer()->notNull(),
            'encounter_class' => $this->string(10)->notNull(),
            'max_pes' => $this->integer()->unsigned()->null()->comment('Tope de PES contratados; null = sin tope explícito'),
            'activo' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
            'deleted_by' => $this->integer()->null(),
        ]);

        $this->createIndex(
            'uq_efector_encounter_entitlement_activo',
            $table,
            ['id_efector', 'encounter_class', 'deleted_at'],
            false
        );
        $this->createIndex('idx_efector_encounter_entitlement_efector', $table, ['id_efector', 'activo']);
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        $table = '{{%efector_encounter_entitlement}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            $this->dropTable($table);
        }
    }
}
