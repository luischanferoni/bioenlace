<?php

use yii\db\Migration;

/**
 * Índice para consultas y unicidad lógica por PES en turnos (persona + fecha + profesional).
 */
class m260508_000006_turnos_index_profesional_efector_servicio extends Migration
{
    public function safeUp()
    {
        $t = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($t === null || !isset($t->columns['id_profesional_efector_servicio'])) {
            return;
        }
        try {
            $this->createIndex(
                'idx_turnos_fecha_persona_id_profesional_efector_servicio',
                '{{%turnos}}',
                ['fecha', 'id_persona', 'id_profesional_efector_servicio']
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                return;
            }
            throw $e;
        }
    }

    public function safeDown()
    {
        $t = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($t === null) {
            return;
        }
        try {
            $this->dropIndex('idx_turnos_fecha_persona_id_profesional_efector_servicio', '{{%turnos}}');
        } catch (\Throwable $e) {
            // índice inexistente u otra variante de motor
        }
    }
}
