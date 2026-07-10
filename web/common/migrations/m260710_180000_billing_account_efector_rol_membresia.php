<?php

use yii\db\Migration;

/**
 * Membresía POOL (consume cupo) vs AFILIADO (solo jerarquía / ministerio).
 */
class m260710_180000_billing_account_efector_rol_membresia extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%billing_account_efector}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['rol_membresia'])) {
            $this->addColumn(
                $table,
                'rol_membresia',
                $this->string(20)->notNull()->defaultValue('POOL')->comment('POOL|AFILIADO')->after('id_efector')
            );
            $this->createIndex('idx_billing_account_efector_rol', $table, ['rol_membresia', 'deleted_at']);
        }

        // Filas existentes = pool (comportamiento previo).
        $this->update($table, ['rol_membresia' => 'POOL'], ['rol_membresia' => null]);
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%billing_account_efector}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['rol_membresia'])) {
            return;
        }
        $this->dropIndex('idx_billing_account_efector_rol', $table);
        $this->dropColumn($table, 'rol_membresia');
    }
}
