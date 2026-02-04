<?php

use yii\db\Migration;
use common\models\Turno;
use common\models\Agenda_rrhh;

/**
 * Agrega tipo_atencion a turnos y acepta_consultas_online a agenda_rrhh para chat paciente-médico.
 */
class m250204_000001_chat_paciente_medico_turnos_rrhh extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Turno::tableName(), 'tipo_atencion', $this->string(20)->defaultValue('presencial')->comment('presencial|teleconsulta'));
        $this->addColumn(Agenda_rrhh::tableName(), 'acepta_consultas_online', $this->boolean()->defaultValue(false)->comment('Si el profesional acepta consultas por chat/videollamada en esta agenda'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn(Turno::tableName(), 'tipo_atencion');
        $this->dropColumn(Agenda_rrhh::tableName(), 'acepta_consultas_online');
    }
}
