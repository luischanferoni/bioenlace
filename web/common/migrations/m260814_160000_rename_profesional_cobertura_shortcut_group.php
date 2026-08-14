<?php

use yii\db\Migration;

/**
 * Etiqueta de grupo de atajos: el usuario ve «horarios», no el término interno cobertura.
 */
class m260814_160000_rename_profesional_cobertura_shortcut_group extends Migration
{
    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%assistant_shortcut_group}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->update($table, [
            'label' => 'Horarios de plantel',
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['group_id' => 'profesional-cobertura']);
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%assistant_shortcut_group}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->update($table, [
            'label' => 'Cobertura',
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['group_id' => 'profesional-cobertura']);
    }
}
