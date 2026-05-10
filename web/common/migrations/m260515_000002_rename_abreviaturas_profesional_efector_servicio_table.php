<?php

use yii\db\Migration;

/**
 * Renombra la tabla puente `abreviaturas_rrhh` → `abreviaturas_profesional_efector_servicio`.
 * Conserva índices existentes (MySQL los mantiene en RENAME).
 */
class m260515_000002_rename_abreviaturas_profesional_efector_servicio_table extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260515_000002: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        if ($this->db->schema->getTableSchema('{{%abreviaturas_rrhh}}', true) === null) {
            echo "m260515_000002: sin tabla abreviaturas_rrhh, omitido.\n";

            return;
        }

        $this->dropFkIfExists('fk_abreviaturas_rrhh_abreviatura', '{{%abreviaturas_rrhh}}');
        $this->renameTable('{{%abreviaturas_rrhh}}', '{{%abreviaturas_profesional_efector_servicio}}');

        $this->addForeignKey(
            'fk_abrev_pes_abreviatura',
            '{{%abreviaturas_profesional_efector_servicio}}',
            'abreviatura_id',
            '{{%abreviaturas_medicas}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        echo "m260515_000002: safeDown no soportado.\n";

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
}
