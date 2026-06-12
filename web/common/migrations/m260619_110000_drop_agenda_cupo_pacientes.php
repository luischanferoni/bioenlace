<?php

use yii\db\Migration;

/**
 * Retira cupo_pacientes: la granularidad de agenda queda en intervalo_minutos + horarios.
 */
class m260619_110000_drop_agenda_cupo_pacientes extends Migration
{
    private const AGENDA = '{{%profesional_efector_servicio_agenda}}';

    private const VERSION = '{{%profesional_efector_servicio_agenda_version}}';

    private const ATTR_FIELD = '{{%data_access_attribute_field}}';

    public function safeUp()
    {
        if ($this->tableHasColumn(self::AGENDA, 'cupo_pacientes')) {
            $this->dropColumn(self::AGENDA, 'cupo_pacientes');
        }
        if ($this->tableHasColumn(self::VERSION, 'cupo_pacientes')) {
            $this->dropColumn(self::VERSION, 'cupo_pacientes');
        }
        if ($this->db->schema->getTableSchema(self::ATTR_FIELD, true) !== null) {
            $this->delete(self::ATTR_FIELD, [
                'entity_group_key' => 'ProfesionalEfectorServicioAgenda.configuracion',
                'field_name' => 'cupo_pacientes',
            ]);
        }
    }

    public function safeDown()
    {
        if (!$this->tableHasColumn(self::AGENDA, 'cupo_pacientes')) {
            $this->addColumn(self::AGENDA, 'cupo_pacientes', $this->integer()->null());
        }
        if (!$this->tableHasColumn(self::VERSION, 'cupo_pacientes')) {
            $this->addColumn(self::VERSION, 'cupo_pacientes', $this->integer()->null());
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
