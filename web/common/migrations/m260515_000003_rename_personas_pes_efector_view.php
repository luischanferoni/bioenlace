<?php

use yii\db\Migration;

/**
 * Renombra la vista `personas_rrhh` → `personas_pes_efector` si existe en el esquema actual.
 */
class m260515_000003_rename_personas_pes_efector_view extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260515_000003: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260515_000003: no se pudo resolver DATABASE().');
        }

        $sql = <<<'SQL'
SELECT COUNT(*) FROM information_schema.TABLES
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'personas_rrhh' AND TABLE_TYPE = 'VIEW'
SQL;
        $n = (int) $this->db->createCommand($sql, [':schema' => (string) $schemaName])->queryScalar();
        if ($n < 1) {
            echo "m260515_000003: vista personas_rrhh no encontrada; omitido (definir vista en BD si aplica).\n";

            return;
        }

        $this->execute('RENAME TABLE `personas_rrhh` TO `personas_pes_efector`');
    }

    public function safeDown()
    {
        echo "m260515_000003: safeDown no soportado.\n";

        return false;
    }
}
