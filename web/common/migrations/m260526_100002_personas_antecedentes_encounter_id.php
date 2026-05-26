<?php

use yii\db\Migration;

/**
 * Renombra `personas_antecedentes.id_consulta` → `encounter_id` (valor = id de `encounter`).
 *
 * Ejecutar antes de {@see m260520_100002_clinical_fhir_drop_legacy} si aplica drop legacy.
 */
class m260526_100002_personas_antecedentes_encounter_id extends Migration
{
    public function safeUp()
    {
        $table = '{{%personas_antecedentes}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->dropFkToConsultas($table, [
            'fk_personas_antecedentes_consulta',
            'personas_antecedentes_ibfk_1',
            'personas_antecedentes_id_consulta_fk',
        ]);

        $this->renameColumnIfExists($table, 'id_consulta', 'encounter_id');
    }

    public function safeDown()
    {
        $table = '{{%personas_antecedentes}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $this->renameColumnIfExists($table, 'encounter_id', 'id_consulta');
    }

    /**
     * @param string[] $fkNames
     */
    private function dropFkToConsultas(string $table, array $fkNames): void
    {
        foreach ($fkNames as $fk) {
            try {
                $this->dropForeignKey($fk, $table);
            } catch (\Throwable $e) {
                // FK puede no existir según entorno
            }
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns[$from])) {
            return;
        }
        if (isset($schema->columns[$to])) {
            return;
        }
        $this->renameColumn($table, $from, $to);
    }
}
