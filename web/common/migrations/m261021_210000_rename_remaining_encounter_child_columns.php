<?php

use yii\db\Migration;

/**
 * Renames remaining encounter references used by demo sandbox cleanup.
 */
class m261021_210000_rename_remaining_encounter_child_columns extends Migration
{
    private const RENAMES = [
        '{{%practicas_personas}}' => ['id_consulta', 'encounter_id'],
        '{{%sumar_autofacturacion_log}}' => ['id_consulta', 'encounter_id'],
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
