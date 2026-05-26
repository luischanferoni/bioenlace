<?php

use yii\db\Migration;

/**
 * Elimina tablas clínicas legacy (greenfield).
 *
 * Requiere {@see m260520_100001_clinical_fhir_prepare_external_refs} y
 * {@see m260526_100002_personas_antecedentes_encounter_id} antes de ejecutar en entornos con datos.
 */
class m260520_100002_clinical_fhir_drop_legacy extends Migration
{
    /** @var string[] orden: hijos antes que padres */
    private const DROP_ORDER = [
        '{{%consultas_ia}}',
        '{{%consultas_alergias}}',
        '{{%consulta_practicas_oftalmologia}}',
        '{{%consultas_receta_lentes}}',
        '{{%consultas_odontologia_practicas}}',
        '{{%consultas_odontologia_diagnosticos}}',
        '{{%consultas_odontologia_estados}}',
        '{{%consultas_balancehidrico}}',
        '{{%consultas_obstetricia}}',
        '{{%consultas_evolucion}}',
        '{{%consultas_sintomas}}',
        '{{%consultas_motivos}}',
        '{{%consultas_suministro_medicamento}}',
        '{{%consultas_regimen}}',
        '{{%consultas_derivaciones}}',
        '{{%consultas_practicas}}',
        '{{%consultas_medicamentos}}',
        '{{%diagnostico_consultas}}',
        '{{%seg_nivel_internacion_medicamento}}',
        '{{%seg_nivel_internacion_practica}}',
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
