<?php

use yii\db\Migration;

/**
 * Elimina la tabla legacy `rr_hh` tras migración completa a {@see \common\models\ProfesionalEfectorServicio}.
 *
 * Ejecutar solo cuando no existan FKs activas hacia `rr_hh` y el código ya no consulte esa tabla.
 */
class m260513_000002_drop_table_rr_hh extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260513_000002: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $raw = $this->db->schema->getRawTableName('{{%rr_hh}}');
        if ($this->db->schema->getTableSchema($raw, true) === null) {
            echo "m260513_000002: tabla rr_hh no existe, omitido.\n";

            return;
        }

        $this->dropTable($raw);
    }

    public function safeDown()
    {
        echo "m260513_000002: rollback no recrea rr_hh.\n";

        return false;
    }
}
