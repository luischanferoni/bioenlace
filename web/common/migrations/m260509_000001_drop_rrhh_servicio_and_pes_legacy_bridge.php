<?php

use yii\db\Migration;

/**
 * Retiro de **`rrhh_servicio`** y del puente **`profesional_efector_servicio.legacy_rrhh_servicio_id`**.
 *
 * ## Prerrequisitos (obligatorio en producción)
 *
 * - Ejecutar {@see web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql} y validar:
 *   - `sin_pes_pero_con_legacy` ≈ 0 en tablas críticas (o filas aceptadas como histórico).
 *   - Chequeos de PES huérfano en 0.
 *   - DDL sin FKs inesperadas hacia `rrhh_servicio` (o incluidas en el descubrimiento dinámico).
 * - **Código de aplicación:** tras aplicar esta migración, cualquier lectura de la tabla eliminada `rrhh_servicio`
 *   fallará; debe existir un despliegue de código alineado (solo PES / sin AR `RrhhServicio`).
 *
 * ## Alcance
 *
 * 1. MySQL/MariaDB: elimina **todas** las FKs que referencian la tabla física `rrhh_servicio` (nombre resuelto vía Yii).
 * 2. `DROP TABLE` `rrhh_servicio` si existe.
 * 3. Quita índice único `ux_pes_legacy_rrhh_servicio_id` y columna `legacy_rrhh_servicio_id` de `profesional_efector_servicio` si existen.
 * 4. Normaliza turnos: pone `id_rrhh_servicio_asignado = 0` donde ya hay `id_profesional_efector_servicio` (dato redundante).
 *
 * ## No incluye (fases posteriores)
 *
 * - Eliminar columnas consumidoras legacy: {@see m260509_000002_drop_legacy_rrhh_servicio_id_columns}.
 * - Tabla `agenda_rrhh`: {@see m260510_000001_drop_agenda_rrhh_table}.
 *
 * ## Rollback
 *
 * No soportado de forma segura (pérdida de definición de `rrhh_servicio`). {@see safeDown} devuelve false.
 */
class m260509_000001_drop_rrhh_servicio_and_pes_legacy_bridge extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260509_000001: omitido (driver {$this->db->driverName}; soportado mysql/mysqli).\n";

            return;
        }

        $rrhhServicioName = $this->db->schema->getRawTableName('{{%rrhh_servicio}}');
        $pesName = $this->db->schema->getRawTableName('{{%profesional_efector_servicio}}');
        $turnosName = $this->db->schema->getRawTableName('{{%turnos}}');

        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            throw new \RuntimeException('m260509_000001: no se pudo resolver DATABASE() para information_schema.');
        }

        $this->dropForeignKeysReferencingTable((string) $schemaName, $rrhhServicioName);

        if ($this->db->schema->getTableSchema('{{%rrhh_servicio}}', true) !== null) {
            $this->dropTable('{{%rrhh_servicio}}');
        }

        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        if ($pes !== null && isset($pes->columns['legacy_rrhh_servicio_id'])) {
            try {
                $this->dropIndex('ux_pes_legacy_rrhh_servicio_id', '{{%profesional_efector_servicio}}');
            } catch (\Throwable $e) {
                // Índice ausente o nombre distinto en entorno antiguo.
            }
            $this->dropColumn('{{%profesional_efector_servicio}}', 'legacy_rrhh_servicio_id');
        }

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null
            && isset($turnos->columns['id_profesional_efector_servicio'])
            && isset($turnos->columns['id_rrhh_servicio_asignado'])
        ) {
            $this->execute("UPDATE {$turnosName} SET id_rrhh_servicio_asignado = 0
                WHERE id_profesional_efector_servicio IS NOT NULL
                  AND id_profesional_efector_servicio <> 0");
        }
    }

    public function safeDown()
    {
        echo "m260509_000001_drop_rrhh_servicio_and_pes_legacy_bridge: safeDown no soportado (restaurar desde backup).\n";

        return false;
    }

    /**
     * @param string $schemaName nombre de la base (DATABASE())
     * @param string $referencedTable nombre físico de la tabla referenciada (p. ej. rrhh_servicio)
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
