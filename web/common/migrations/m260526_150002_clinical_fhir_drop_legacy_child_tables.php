<?php

use yii\db\Migration;

/**
 * Drop tablas hijas legacy (solo cuando captura/reportes usen 100 % recursos FHIR).
 *
 * NO ejecutar en producción mientras existan lecturas a
 * `ConsultaMotivos`, `ConsultaDerivaciones`, `DiagnosticoConsulta`, odontología legacy, etc.
 *
 * Orden: hijos antes que padres. La tabla `consultas` ya debe estar eliminada
 * ({@see m260520_100002_clinical_fhir_drop_legacy}).
 */
class m260526_150002_clinical_fhir_drop_legacy_child_tables extends Migration
{
    /** @var string[] */
    private const DROP_ORDER = [
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
    ];

    public function safeUp()
    {
        foreach (self::DROP_ORDER as $table) {
            $this->dropTableIfExists($table);
        }
    }

    public function safeDown()
    {
        echo "    > m260526_150002: safeDown no recrea tablas legacy.\n";

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
