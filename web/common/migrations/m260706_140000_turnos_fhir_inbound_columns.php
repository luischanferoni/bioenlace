<?php

use yii\db\Migration;

/**
 * Espejo local de Appointment FHIR entrante en turnos.
 */
class m260706_140000_turnos_fhir_inbound_columns extends Migration
{
    public function safeUp()
    {
        $table = '{{%turnos}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['external_appointment_id'])) {
            $this->addColumn($table, 'external_appointment_id', $this->string(128)->null()
                ->comment('Appointment.id en servidor FHIR externo'));
        }
        if (!isset($schema->columns['appointment_source_system'])) {
            $this->addColumn($table, 'appointment_source_system', $this->string(64)->null()
                ->comment('Clave conector: msal-nis, …'));
        }
        if (!isset($schema->columns['external_schedule_id'])) {
            $this->addColumn($table, 'external_schedule_id', $this->string(128)->null()
                ->comment('Schedule.id HAPI vinculado a la cita'));
        }
        if (!isset($schema->columns['pes_resolution_trust'])) {
            $this->addColumn($table, 'pes_resolution_trust', $this->string(16)->null()
                ->comment('verified|provisional|unresolved|stale'));
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['id_persona']) && !$schema->columns['id_persona']->allowNull) {
            $this->alterColumn($table, 'id_persona', $this->integer()->null());
        }

        if (!$this->indexExists($table, 'ux_turnos_external_appointment')) {
            $this->createIndex(
                'ux_turnos_external_appointment',
                $table,
                ['appointment_source_system', 'external_appointment_id'],
                true
            );
        }

        $sync = '{{%integration_fhir_sync_state}}';
        if ($this->db->schema->getTableSchema($sync, true) === null) {
            $opts = $this->db->driverName === 'mysql'
                ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
                : null;
            $this->createTable($sync, [
                'source_system' => $this->string(64)->notNull(),
                'last_success_at' => $this->dateTime()->null(),
                'last_cursor' => $this->string(64)->null()->comment('Instante _lastUpdated FHIR'),
                'last_error' => $this->text()->null(),
                'updated_at' => $this->dateTime()->notNull(),
            ], $opts);
            $this->addPrimaryKey('pk_integration_fhir_sync_state', $sync, 'source_system');
        }
    }

    public function safeDown()
    {
        $sync = '{{%integration_fhir_sync_state}}';
        if ($this->db->schema->getTableSchema($sync, true) !== null) {
            $this->dropTable($sync);
        }

        $table = '{{%turnos}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if ($this->indexExists($table, 'ux_turnos_external_appointment')) {
            $this->dropIndex('ux_turnos_external_appointment', $table);
        }
        foreach (['pes_resolution_trust', 'external_schedule_id', 'appointment_source_system', 'external_appointment_id'] as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($table, $col);
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($raw, true);

        return isset($indexes[$name]);
    }
}
