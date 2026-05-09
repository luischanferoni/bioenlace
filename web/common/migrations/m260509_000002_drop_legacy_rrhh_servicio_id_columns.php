<?php

use yii\db\Migration;

/**
 * Retiro de columnas que almacenaban ids de **`rrhh_servicio`** o el “slot legacy” tras PES canónico.
 *
 * ## Tablas / columnas
 *
 * - `turnos.id_rrhh_servicio_asignado`
 * - `guardia.id_rrhh_asignado`
 * - `documentos_externos.id_rrhh_servicio`
 * - `atenciones_enfermeria.id_rrhh_servicio`
 *
 * ## Antes de aplicar
 *
 * - Código desplegado alineado (sin lectura/escritura AR de estas columnas).
 * - Datos: filas deben tener `id_profesional_efector_servicio` cuando el negocio lo exige; esta migración
 *   intenta backfill conservador (PES por PK, vínculo RRHH–efector, etc.) antes del `DROP`.
 *
 * ## Rollback
 *
 * No soportado.
 */
class m260509_000002_drop_legacy_rrhh_servicio_id_columns extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260509_000002: omitido (driver {$this->db->driverName}; soportado mysql/mysqli).\n";

            return;
        }

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260509_000002: no se pudo resolver DATABASE().');
        }
        $schemaName = (string) $schemaName;

        $this->backfillTurnosPesDesdeLegacyAsignado();
        $this->backfillDocumentosExternosPes();
        $this->backfillGuardiaPes();
        $this->backfillAtencionesEnfermeriaPes();

        $this->dropColumnIfPresent($schemaName, '{{%turnos}}', 'id_rrhh_servicio_asignado');
        $this->dropColumnIfPresent($schemaName, '{{%guardia}}', 'id_rrhh_asignado');
        $this->dropColumnIfPresent($schemaName, '{{%documentos_externos}}', 'id_rrhh_servicio');
        $this->dropColumnIfPresent($schemaName, '{{%atenciones_enfermeria}}', 'id_rrhh_servicio');
    }

    public function safeDown()
    {
        echo "m260509_000002_drop_legacy_rrhh_servicio_id_columns: safeDown no soportado.\n";

        return false;
    }

    private function backfillTurnosPesDesdeLegacyAsignado(): void
    {
        $t = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($t === null || !isset($t->columns['id_rrhh_servicio_asignado'])) {
            return;
        }
        $this->execute(<<<SQL
UPDATE {{%turnos}} t
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id = t.id_rrhh_servicio_asignado AND pes.deleted_at IS NULL
SET t.id_profesional_efector_servicio = pes.id
WHERE (t.id_profesional_efector_servicio IS NULL OR t.id_profesional_efector_servicio = 0)
  AND t.id_rrhh_servicio_asignado > 0
  AND (t.id_efector IS NULL OR pes.id_efector = t.id_efector)
SQL);
    }

    private function backfillDocumentosExternosPes(): void
    {
        $t = $this->db->schema->getTableSchema('{{%documentos_externos}}', true);
        if ($t === null || !isset($t->columns['id_rrhh_servicio'])) {
            return;
        }
        $this->execute(<<<SQL
UPDATE {{%documentos_externos}} x
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id = x.id_rrhh_servicio AND pes.deleted_at IS NULL
SET x.id_profesional_efector_servicio = pes.id
WHERE (x.id_profesional_efector_servicio IS NULL OR x.id_profesional_efector_servicio = 0)
  AND x.id_rrhh_servicio > 0
  AND (x.id_efector IS NULL OR pes.id_efector = x.id_efector)
SQL);
    }

    private function backfillGuardiaPes(): void
    {
        $t = $this->db->schema->getTableSchema('{{%guardia}}', true);
        if ($t === null || !isset($t->columns['id_rrhh_asignado'])) {
            return;
        }
        $this->execute(<<<SQL
UPDATE {{%guardia}} g
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id = g.id_rrhh_asignado AND pes.deleted_at IS NULL
SET g.id_profesional_efector_servicio = pes.id
WHERE (g.id_profesional_efector_servicio IS NULL OR g.id_profesional_efector_servicio = 0)
  AND g.id_rrhh_asignado > 0
  AND (g.id_efector IS NULL OR pes.id_efector = g.id_efector)
SQL);
        $this->execute(<<<SQL
UPDATE {{%guardia}} g
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = g.id_rrhh_asignado AND re.id_efector = g.id_efector AND re.deleted_at IS NULL
INNER JOIN (
  SELECT id_persona, id_efector, MIN(id) AS id_pes
  FROM {{%profesional_efector_servicio}}
  WHERE deleted_at IS NULL
  GROUP BY id_persona, id_efector
) pick ON pick.id_persona = re.id_persona AND pick.id_efector = re.id_efector
SET g.id_profesional_efector_servicio = pick.id_pes
WHERE (g.id_profesional_efector_servicio IS NULL OR g.id_profesional_efector_servicio = 0)
  AND g.id_rrhh_asignado > 0
  AND g.id_efector IS NOT NULL
SQL);
    }

    private function backfillAtencionesEnfermeriaPes(): void
    {
        $t = $this->db->schema->getTableSchema('{{%atenciones_enfermeria}}', true);
        if ($t === null || !isset($t->columns['id_rrhh_servicio'])) {
            return;
        }
        $this->execute(<<<SQL
UPDATE {{%atenciones_enfermeria}} a
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id = a.id_rrhh_servicio AND pes.deleted_at IS NULL
INNER JOIN {{%consultas}} c ON c.id_consulta = a.id_consulta
SET a.id_profesional_efector_servicio = pes.id
WHERE (a.id_profesional_efector_servicio IS NULL OR a.id_profesional_efector_servicio = 0)
  AND a.id_rrhh_servicio > 0
  AND pes.id_efector = c.id_efector
SQL);
    }

    private function dropColumnIfPresent(string $schemaName, string $table, string $column): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $raw = $this->db->schema->getRawTableName($table);
        $this->dropForeignKeysOnColumn($schemaName, $raw, $column);
        $this->dropIndexesUsingColumn($schemaName, $raw, $column, $table);
        $this->dropColumn($table, $column);
    }

    private function dropForeignKeysOnColumn(string $schemaName, string $rawTable, string $column): void
    {
        $sql = <<<'SQL'
SELECT DISTINCT CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = :schema
  AND TABLE_NAME = :table
  AND COLUMN_NAME = :col
  AND REFERENCED_TABLE_NAME IS NOT NULL
SQL;
        $rows = $this->db->createCommand($sql, [
            ':schema' => $schemaName,
            ':table' => $rawTable,
            ':col' => $column,
        ])->queryAll();
        foreach ($rows as $row) {
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $row['CONSTRAINT_NAME']);
            if ($c === '') {
                continue;
            }
            try {
                $this->execute("ALTER TABLE `{$rawTable}` DROP FOREIGN KEY `{$c}`");
            } catch (\Throwable $e) {
                \Yii::warning("m260509_000002: no se pudo DROP FK {$c} en {$rawTable}: " . $e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * Elimina índices (no PRIMARY) que incluyen la columna. En tablas con índices compuestos,
     * se elimina el índice completo (recrear índices alternativos queda fuera de alcance).
     */
    private function dropIndexesUsingColumn(string $schemaName, string $rawTable, string $column, string $yiiTable): void
    {
        $sql = <<<'SQL'
SELECT DISTINCT INDEX_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = :schema
  AND TABLE_NAME = :table
  AND COLUMN_NAME = :col
  AND INDEX_NAME != 'PRIMARY'
SQL;
        $rows = $this->db->createCommand($sql, [
            ':schema' => $schemaName,
            ':table' => $rawTable,
            ':col' => $column,
        ])->queryAll();
        foreach ($rows as $row) {
            $idx = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $row['INDEX_NAME']);
            if ($idx === '') {
                continue;
            }
            try {
                $this->dropIndex($idx, $yiiTable);
            } catch (\Throwable $e) {
                \Yii::warning("m260509_000002: no se pudo DROP INDEX {$idx} en {$rawTable}: " . $e->getMessage(), __METHOD__);
            }
        }
    }
}
