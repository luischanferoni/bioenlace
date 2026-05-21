<?php

use yii\db\Migration;

/**
 * Columnas opcionales Appointment (FHIR) en turnos — sin romper flujos actuales.
 */
class m260520_100003_turnos_appointment_fhir_columns extends Migration
{
    public function safeUp()
    {
        $table = '{{%turnos}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if (!isset($schema->columns['fhir_status'])) {
            $this->addColumn($table, 'fhir_status', $this->string(32)->null()->comment('FHIR AppointmentStatus'));
        }
        if (!isset($schema->columns['appointment_type'])) {
            $this->addColumn($table, 'appointment_type', $this->string(64)->null()->comment('Tipo de cita (código interno o FHIR)'));
        }
    }

    public function safeDown()
    {
        $table = '{{%turnos}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['appointment_type'])) {
            $this->dropColumn($table, 'appointment_type');
        }
        if (isset($schema->columns['fhir_status'])) {
            $this->dropColumn($table, 'fhir_status');
        }
    }
}
