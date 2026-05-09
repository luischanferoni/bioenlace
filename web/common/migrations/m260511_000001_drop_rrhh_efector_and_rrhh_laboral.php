<?php

use yii\db\Migration;

/**
 * Elimina las tablas **`rrhh_laboral`** y **`rrhh_efector`** (vínculo persona–efector vía RRHH y condiciones laborales asociadas).
 *
 * ## Advertencia (obligatoria)
 *
 * **Antes de aplicar:** el código ya debe estar migrado a PES/`rr_hh` y sin modelos AR legacy (`RrhhEfector`/`RrhhLaboral` eliminados del código).
 * Ejecutar **`m260512_000001_drop_rrhh_legacy_columns_post_pes`** en el mismo orden de despliegue que este archivo cuando corresponda el retiro de columnas.
 *
 * ## Prerrequisitos sugeridos
 *
 * - Backup y revisión de FKs: `web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql` (y variantes de entorno).
 * - Código sin consultas ni AR a estas tablas; columnas `id_rrhh_efector` / joins eliminados o sustituidos donde correspondan.
 * - Migraciones PES previas aplicadas (`m260509_*`, `m260510_*`, etc.) según el plan del entorno.
 * - Opcional: script orientativo `web/docs/sql/retiro_legacy_rrhh_post_pes.sql` (fases de columnas antes de DROP de tablas).
 *
 * ## Alcance
 *
 * 1. MySQL/MariaDB: elimina **todas** las FKs que referencian `rrhh_laboral`, luego `DROP TABLE` si existe.
 * 2. Igual para `rrhh_efector`.
 *
 * No modifica la tabla `rr_hh` ni columnas `id_rr_hh` en otras tablas salvo que existan FKs InnoDB hacia estas tablas
 * (en cuyo caso solo se elimina la restricción para poder hacer el DROP).
 *
 * ## Rollback
 *
 * No soportado.
 */
class m260511_000001_drop_rrhh_efector_and_rrhh_laboral extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260511_000001: omitido (driver {$this->db->driverName}; soportado mysql/mysqli).\n";

            return;
        }

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260511_000001: no se pudo resolver DATABASE() para information_schema.');
        }
        $schemaName = (string) $schemaName;

        $laboralRaw = $this->db->schema->getRawTableName('{{%rrhh_laboral}}');
        $efectorRaw = $this->db->schema->getRawTableName('{{%rrhh_efector}}');

        $this->dropForeignKeysReferencingTable($schemaName, $laboralRaw);
        if ($this->db->schema->getTableSchema('{{%rrhh_laboral}}', true) !== null) {
            $this->dropTable('{{%rrhh_laboral}}');
        }

        $this->dropForeignKeysReferencingTable($schemaName, $efectorRaw);
        if ($this->db->schema->getTableSchema('{{%rrhh_efector}}', true) !== null) {
            $this->dropTable('{{%rrhh_efector}}');
        }
    }

    public function safeDown()
    {
        echo "m260511_000001_drop_rrhh_efector_and_rrhh_laboral: safeDown no soportado (restaurar desde backup).\n";

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
                \Yii::warning("m260511_000001: no se pudo DROP FOREIGN KEY {$c} en {$t}: " . $e->getMessage(), __METHOD__);
            }
        }
    }
}
