<?php

use yii\db\Migration;

/**
 * Elimina la tabla padre `consultas` y configuración legacy duplicada.
 *
 * Las tablas hijas (`consultas_motivos`, `consultas_derivaciones`, …) siguen en uso:
 * la columna `id_consulta` almacena {@see \common\models\Clinical\Encounter::$id}.
 * El drop de hijas (→ solo FHIR) queda en
 * {@see m260526_150002_clinical_fhir_drop_legacy_child_tables} cuando el código deje de leerlas.
 *
 * Requiere {@see m260520_100001_clinical_fhir_prepare_external_refs} y
 * {@see m260526_100002_personas_antecedentes_encounter_id} antes de ejecutar en entornos con datos.
 */
class m260520_100002_clinical_fhir_drop_legacy extends Migration
{
    /** @var string[] tablas sin filas operativas post-migración FHIR */
    private const DROP_ORDER = [
        '{{%consultas_ia}}',
        '{{%consultas_configuracion}}',
        '{{%consultas}}',
    ];

    public function safeUp()
    {
        foreach (self::DROP_ORDER as $table) {
            $this->dropTableIfExists($table);
        }
    }

    public function safeDown()
    {
        echo "    > m260520_100002: safeDown no recrea tablas legacy (greenfield).\n";

        return true;
    }

    private function dropTableIfExists(string $table): void
    {
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $this->dropTable($table);
    }
}
