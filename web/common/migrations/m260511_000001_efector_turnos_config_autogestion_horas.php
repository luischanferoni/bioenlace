<?php

use yii\db\Migration;

/**
 * Anticipación mínima (horas) para cancelar / reprogramar por app por efector.
 * NULL = usar default global en {@see \Yii::$app->params} `efectorTurnosConfigDefaults`;
 * 0 = sin restricción por anticipación.
 */
class m260511_000001_efector_turnos_config_autogestion_horas extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%efector_turnos_config}}', true);
        if ($schema === null) {
            return;
        }
        if (!isset($schema->columns['autogestion_min_horas_antes_cancelar'])) {
            $this->addColumn(
                '{{%efector_turnos_config}}',
                'autogestion_min_horas_antes_cancelar',
                $this->integer()->unsigned()->null()->comment('Horas mínimas antes del turno para cancelar por app; NULL=default params; 0=sin límite')
            );
            $this->db->schema->refreshTableSchema('{{%efector_turnos_config}}');
            $schema = $this->db->schema->getTableSchema('{{%efector_turnos_config}}', true);
        }
        if (!isset($schema->columns['autogestion_min_horas_antes_reprogramar'])) {
            $this->addColumn(
                '{{%efector_turnos_config}}',
                'autogestion_min_horas_antes_reprogramar',
                $this->integer()->unsigned()->null()->comment('Horas mínimas antes del turno para reprogramar por app; NULL=default params; 0=sin límite')
            );
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%efector_turnos_config}}', true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['autogestion_min_horas_antes_reprogramar'])) {
            $this->dropColumn('{{%efector_turnos_config}}', 'autogestion_min_horas_antes_reprogramar');
        }
        if (isset($schema->columns['autogestion_min_horas_antes_cancelar'])) {
            $this->dropColumn('{{%efector_turnos_config}}', 'autogestion_min_horas_antes_cancelar');
        }
    }
}
