<?php

use yii\db\Migration;

/**
 * Retiro de columnas legacy RRHH post-PES (alineado a `web/docs/sql/retiro_legacy_rrhh_post_pes.sql`).
 *
 * ## Pre-requisitos
 *
 * - Diagnóstico OK (`web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql`).
 * - Despliegue de código sin lectura/escritura AR de las columnas listadas (mismo release que esta migración).
 *
 * ## Notas
 *
 * - Columnas ya eliminadas por migraciones previas (`m260509_000002`, etc.) se omiten vía `dropColumnIfPresent`.
 * - Sin rollback seguro.
 *
 * ## Orden vs tablas
 *
 * Ejecutar **antes** de {@see m260511_000001_drop_rrhh_efector_and_rrhh_laboral}.
 */
class m260512_000001_drop_rrhh_legacy_columns_post_pes extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260512_000001: omitido (driver {$this->db->driverName}; soportado mysql/mysqli).\n";

            return;
        }

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260512_000001: no se pudo resolver DATABASE().');
        }
        $schemaName = (string) $schemaName;

        $this->dropColumnIfPresent($schemaName, '{{%abreviaturas_rrhh}}', 'id_rr_hh');

        foreach (['id_rr_hh', 'id_rrhh_servicio'] as $col) {
            $this->dropColumnIfPresent($schemaName, '{{%atenciones_enfermeria}}', $col);
        }

        $this->dropColumnIfPresent($schemaName, '{{%consultas}}', 'id_rr_hh');
        $this->dropColumnIfPresent($schemaName, '{{%consultas_derivaciones}}', 'id_rr_hh');
        $this->dropColumnIfPresent($schemaName, '{{%consultas_suministro_medicamento}}', 'id_rrhh');

        $this->dropColumnIfPresent($schemaName, '{{%dispensa_programa_diabetes}}', 'id_rrhh_efector');

        $this->dropColumnIfPresent($schemaName, '{{%documentos_externos}}', 'id_rrhh_servicio');

        $this->dropColumnIfPresent($schemaName, '{{%encuesta_parches_mamarios}}', 'id_rr_hh');

        foreach (['id_rrhh_asignado', 'id_rr_hh'] as $col) {
            $this->dropColumnIfPresent($schemaName, '{{%guardia}}', $col);
        }

        $this->dropColumnIfPresent($schemaName, '{{%persona_programa}}', 'id_rrhh_efector');
        $this->dropColumnIfPresent($schemaName, '{{%persona_programa_diabetes}}', 'id_rrhh_efector');

        $this->dropColumnIfPresent($schemaName, '{{%seg_nivel_internacion}}', 'id_rrhh');

        foreach (['id_rrhh_solicita', 'id_rrhh_realiza'] as $col) {
            $this->dropColumnIfPresent($schemaName, '{{%seg_nivel_internacion_practica}}', $col);
        }

        $this->dropColumnIfPresent($schemaName, '{{%sumar_autofacturacion}}', 'id_rr_hh');

        foreach (['id_rr_hh', 'id_rrhh_servicio_asignado'] as $col) {
            $this->dropColumnIfPresent($schemaName, '{{%turnos}}', $col);
        }

        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        if ($pes !== null && isset($pes->columns['legacy_rrhh_servicio_id'])) {
            $this->dropColumnIfPresent($schemaName, '{{%profesional_efector_servicio}}', 'legacy_rrhh_servicio_id');
        }
    }

    public function safeDown()
    {
        echo "m260512_000001_drop_rrhh_legacy_columns_post_pes: safeDown no soportado.\n";

        return false;
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
                \Yii::warning("m260512_000001: no se pudo DROP FK {$c} en {$rawTable}: " . $e->getMessage(), __METHOD__);
            }
        }
    }

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
                \Yii::warning("m260512_000001: no se pudo DROP INDEX {$idx} en {$rawTable}: " . $e->getMessage(), __METHOD__);
            }
        }
    }
}
