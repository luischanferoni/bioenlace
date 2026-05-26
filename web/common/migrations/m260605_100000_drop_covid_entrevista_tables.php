<?php

use yii\db\Migration;

/**
 * Retiro módulo COVID entrevista telefónica (clean-legacy Fase 02).
 *
 * Controller y vistas eliminados en Fase 01. Sin referencias en API ni asistente.
 * Plan: web/docs/plans/clean-legacy/
 */
class m260605_100000_drop_covid_entrevista_tables extends Migration
{
    private const TABLES = [
        '{{%covid_factores_riesgo}}',
        '{{%covid_investigacion_epidemiologica}}',
        '{{%covid_entrevista_telefonica}}',
    ];

    public function safeUp()
    {
        foreach (self::TABLES as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }

    public function safeDown()
    {
        echo "m260605_100000: no se recrean tablas covid_entrevista_*.\n";
    }
}
