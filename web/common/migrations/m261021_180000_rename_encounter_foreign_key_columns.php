<?php

use yii\db\Migration;

/**
 * Renames active encounter-reference columns to their canonical names.
 */
class m261021_180000_rename_encounter_foreign_key_columns extends Migration
{
    private const RENAMES = [
        '{{%referencia}}' => ['legacy_id_consulta', 'encounter_id'],
        '{{%sumar_autofacturacion}}' => ['legacy_id_consulta', 'encounter_id'],
        '{{%tension_arterial}}' => ['id_consulta', 'encounter_id'],
        '{{%valoracion_nutricional}}' => ['id_consulta', 'encounter_id'],
        '{{%odonto_consulta_persona}}' => ['id_consulta', 'encounter_id'],
        '{{%turnos}}' => ['id_consulta_referencia', 'referenced_encounter_id'],
    ];

    public function safeUp(): void
    {
        foreach (self::RENAMES as $table => [$oldColumn, $newColumn]) {
            $this->renameColumnIfPresent($table, $oldColumn, $newColumn);
        }
    }

    public function safeDown(): void
    {
        foreach (array_reverse(self::RENAMES, true) as $table => [$oldColumn, $newColumn]) {
            $this->renameColumnIfPresent($table, $newColumn, $oldColumn);
        }
    }

    private function renameColumnIfPresent(string $table, string $from, string $to): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns[$from])) {
            return;
        }
        if (isset($schema->columns[$to])) {
            throw new RuntimeException(
                sprintf('Cannot rename %s.%s: column %s already exists.', $table, $from, $to)
            );
        }

        $this->renameColumn($table, $from, $to);
    }
}
