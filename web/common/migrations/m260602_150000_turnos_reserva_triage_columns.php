<?php

use yii\db\Migration;

/**
 * Triage de reserva (paciente): motivo estructurado antes de confirmar turno.
 */
class m260602_150000_turnos_reserva_triage_columns extends Migration
{
    public function safeUp()
    {
        $table = '{{%turnos}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if (!isset($schema->columns['reserva_triage_code'])) {
            $this->addColumn(
                $table,
                'reserva_triage_code',
                $this->string(64)->null()->comment('Código hoja del catálogo reserva_triage')
            );
        }
        if (!isset($schema->columns['urgency_band'])) {
            $this->addColumn(
                $table,
                'urgency_band',
                $this->char(1)->null()->comment('Banda A–D del triage de reserva')
            );
        }
        if (!isset($schema->columns['reserva_triage_meta_json'])) {
            $this->addColumn(
                $table,
                'reserva_triage_meta_json',
                $this->json()->null()->comment('Trayectoria de selección + nota libre')
            );
        }
    }

    public function safeDown()
    {
        $table = '{{%turnos}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        foreach (['reserva_triage_meta_json', 'urgency_band', 'reserva_triage_code'] as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($table, $col);
            }
        }
    }
}
