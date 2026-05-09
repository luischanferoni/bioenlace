<?php

use yii\db\Migration;

/**
 * Elimina la tabla legado **`agenda_rrhh`** (agenda por fila duplicada frente a `profesional_efector_servicio_agenda`).
 *
 * ## Prerrequisitos
 *
 * - Migraciones PES anteriores aplicadas; consumo de agenda solo vía `ProfesionalEfectorServicioAgenda` / API profesional-agenda.
 * - Opcional: `information_schema` sin dependencias de negocio activas hacia `agenda_rrhh` (la migración elimina FKs que referencian esta tabla).
 *
 * ## Alcance
 *
 * 1. MySQL/MariaDB: `DROP FOREIGN KEY` en tablas que referencian `agenda_rrhh`.
 * 2. `DROP TABLE` `agenda_rrhh` si existe.
 *
 * ## Rollback
 *
 * No soportado.
 */
class m260510_000001_drop_agenda_rrhh_table extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260510_000001: omitido (driver {$this->db->driverName}; soportado mysql/mysqli).\n";

            return;
        }

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260510_000001: no se pudo resolver DATABASE() para information_schema.');
        }

        $agendaRrhhName = $this->db->schema->getRawTableName('{{%agenda_rrhh}}');
        $this->dropForeignKeysReferencingTable((string) $schemaName, $agendaRrhhName);

        if ($this->db->schema->getTableSchema('{{%agenda_rrhh}}', true) !== null) {
            $this->dropTable('{{%agenda_rrhh}}');
        }
    }

    public function safeDown()
    {
        echo "m260510_000001_drop_agenda_rrhh_table: safeDown no soportado (restaurar desde backup).\n";

        return false;
    }

    /**
     * @param string $schemaName nombre de la base (DATABASE())
     * @param string $referencedTable nombre físico de la tabla referenciada
     */
    private function dropForeignKeysReferencingTable(string $schemaName, string $referencedTable): void
    {
        $sql = <<<'SQL'
SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = :schema
  AND REFERENCED_TABLE_SCHEMA = :schema
  AND REFERENCED_TABLE_NAME = :refTable
  AND CONSTRAINT_NAME IS NOT NULL
SQL;
        $rows = $this->db->createCommand($sql, [
            ':schema' => $schemaName,
            ':refTable' => $referencedTable,
        ])->queryAll();

        foreach ($rows as $row) {
            $t = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $row['TABLE_NAME']);
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $row['CONSTRAINT_NAME']);
            if ($t === '' || $c === '') {
                continue;
            }
            try {
                $this->execute("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$c}`");
            } catch (\Throwable $e) {
                \Yii::warning("No se pudo DROP FOREIGN KEY {$c} en {$t}: " . $e->getMessage(), __METHOD__);
            }
        }
    }
}
