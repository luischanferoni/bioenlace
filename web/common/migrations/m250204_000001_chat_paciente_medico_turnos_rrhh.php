<?php

use yii\db\Migration;

/**
 * Agrega tipo_atencion a turnos y acepta_consultas_online a rr_hh para chat paciente-médico.
 */
class m250204_000001_chat_paciente_medico_turnos_rrhh extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%turnos}}', 'tipo_atencion', $this->string(20)->defaultValue('presencial')->comment('presencial|teleconsulta'));
        $this->addColumn('{{%rr_hh}}', 'acepta_consultas_online', $this->boolean()->defaultValue(false)->comment('Si el profesional acepta consultas por chat/videollamada'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%turnos}}', 'tipo_atencion');
        $this->dropColumn('{{%rr_hh}}', 'acepta_consultas_online');
    }
}
