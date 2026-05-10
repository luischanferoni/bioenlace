<?php

use yii\db\Migration;

/**
 * Renombra tablas y columnas legacy `solicitud_rrhh*` → nomenclatura PES.
 *
 * Orden: eliminar FK hija→padre, renombrar tablas, renombrar columnas, recrear FK e índices.
 */
class m260515_000001_rename_solicitud_profesional_efector_tables extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260515_000001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh}}', true) === null) {
            echo "m260515_000001: sin tabla solicitud_rrhh, omitido.\n";

            return;
        }

        $this->dropFkIfExists('fk_solicitud_rrhh_evento_sol', '{{%solicitud_rrhh_evento}}');

        $this->renameTable('{{%solicitud_rrhh}}', '{{%solicitud_profesional_efector}}');

        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh_evento}}', true) !== null) {
            $this->renameTable('{{%solicitud_rrhh_evento}}', '{{%solicitud_profesional_efector_evento}}');
        }

        $pes = $this->db->schema->getTableSchema('{{%solicitud_profesional_efector}}', true);
        if ($pes !== null) {
            if (isset($pes->columns['id_solicitante_rr_hh'])) {
                $this->execute('ALTER TABLE {{%solicitud_profesional_efector}} CHANGE COLUMN `id_solicitante_rr_hh` `id_solicitante_profesional_efector_servicio` INT NOT NULL');
            }
            if (isset($pes->columns['id_destinatario_rr_hh'])) {
                $this->execute('ALTER TABLE {{%solicitud_profesional_efector}} CHANGE COLUMN `id_destinatario_rr_hh` `id_destinatario_profesional_efector_servicio` INT NULL DEFAULT NULL');
            }
        }

        $this->dropIndexIfExists('idx_solicitud_rrhh_efector', '{{%solicitud_profesional_efector}}');
        $this->dropIndexIfExists('idx_solicitud_rrhh_estado', '{{%solicitud_profesional_efector}}');
        $this->createIndex('idx_sol_prof_ef_efector', '{{%solicitud_profesional_efector}}', 'id_efector');
        $this->createIndex('idx_sol_prof_ef_estado', '{{%solicitud_profesional_efector}}', 'estado');

        $ev = $this->db->schema->getTableSchema('{{%solicitud_profesional_efector_evento}}', true);
        if ($ev !== null) {
            $this->dropIndexIfExists('idx_solicitud_rrhh_evento_sol', '{{%solicitud_profesional_efector_evento}}');
            $this->createIndex('idx_sol_prof_ef_ev_sol', '{{%solicitud_profesional_efector_evento}}', 'id_solicitud');
            $this->addForeignKey(
                'fk_sol_prof_ef_ev_sol',
                '{{%solicitud_profesional_efector_evento}}',
                'id_solicitud',
                '{{%solicitud_profesional_efector}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        echo "m260515_000001_rename_solicitud_profesional_efector_tables: safeDown no soportado.\n";

        return false;
    }

    private function dropFkIfExists(string $name, string $table): void
    {
        $raw = $this->db->schema->getRawTableName($table);
        $schemaName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($schemaName === false || $schemaName === null || $schemaName === '') {
            return;
        }
        $sql = <<<'SQL'
SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :t AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = :c
SQL;
        $row = $this->db->createCommand($sql, [
            ':schema' => (string) $schemaName,
            ':t' => $raw,
            ':c' => $name,
        ])->queryScalar();
        if ($row === false || $row === null) {
            return;
        }
        $this->dropForeignKey($name, $table);
    }

    private function dropIndexIfExists(string $name, string $table): void
    {
        try {
            $this->dropIndex($name, $table);
        } catch (\Throwable $e) {
            // índice ausente o nombre distinto según versión
        }
    }
}
