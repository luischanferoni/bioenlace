<?php

use yii\db\Migration;

/**
 * Desacopla tablas no clínicas de `consultas` antes del DROP legacy.
 * Renombra columnas a `encounter_id` o `legacy_id_consulta` (sin FK a consultas).
 */
class m260520_100001_clinical_fhir_prepare_external_refs extends Migration
{
    public function safeUp()
    {
        $this->dropFkToConsultas('{{%interaccion_motivos_consulta}}', [
            'fk_consulta_motivos_messages_consulta',
            'fk_interaccion_motivos_consulta_consulta',
        ]);
        $this->renameColumnIfExists('{{%interaccion_motivos_consulta}}', 'consulta_id', 'encounter_id');

        $this->dropFkToConsultas('{{%interaccion_chat_clinico}}', [
            'fk_consulta_chat_messages_consulta',
            'fk_interaccion_chat_clinico_consulta',
        ]);
        $this->renameColumnIfExists('{{%interaccion_chat_clinico}}', 'consulta_id', 'encounter_id');

        $this->renameColumnIfExists('{{%snomed_deferred_jobs}}', 'consulta_id', 'encounter_id');

        $this->renameColumnIfExists('{{%referencia}}', 'id_consulta', 'legacy_id_consulta');
        $this->renameColumnIfExists('{{%sumar_autofacturacion}}', 'id_consulta', 'legacy_id_consulta');
        $this->renameColumnIfExists('{{%atenciones_enfermeria}}', 'id_consulta', 'encounter_id');
    }

    public function safeDown()
    {
        $this->renameColumnIfExists('{{%atenciones_enfermeria}}', 'encounter_id', 'id_consulta');
        $this->renameColumnIfExists('{{%sumar_autofacturacion}}', 'legacy_id_consulta', 'id_consulta');
        $this->renameColumnIfExists('{{%referencia}}', 'legacy_id_consulta', 'id_consulta');
        $this->renameColumnIfExists('{{%snomed_deferred_jobs}}', 'encounter_id', 'consulta_id');
        $this->renameColumnIfExists('{{%interaccion_chat_clinico}}', 'encounter_id', 'consulta_id');
        $this->renameColumnIfExists('{{%interaccion_motivos_consulta}}', 'encounter_id', 'consulta_id');
    }

    /**
     * @param string[] $fkNames
     */
    private function dropFkToConsultas(string $table, array $fkNames): void
    {
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
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
