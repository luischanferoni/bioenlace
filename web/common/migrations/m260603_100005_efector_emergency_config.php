<?php

use yii\db\Migration;

/**
 * Umbrales SLA de guardia por efector (minutos).
 */
class m260603_100005_efector_emergency_config extends Migration
{
    public function safeUp()
    {
        $t = '{{%efector_emergency_config}}';
        if ($this->db->schema->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id_efector' => $this->primaryKey(),
                'minutos_espera_triage' => $this->integer()->notNull()->defaultValue(15),
                'minutos_espera_medico_1' => $this->integer()->notNull()->defaultValue(0),
                'minutos_espera_medico_2' => $this->integer()->notNull()->defaultValue(10),
                'minutos_espera_medico_3' => $this->integer()->notNull()->defaultValue(60),
                'minutos_espera_medico_4' => $this->integer()->notNull()->defaultValue(120),
                'minutos_espera_medico_5' => $this->integer()->notNull()->defaultValue(240),
                'updated_at' => $this->dateTime()->null(),
            ]);
        }

        $internacion = '{{%seg_nivel_internacion}}';
        $schema = $this->db->schema->getTableSchema($internacion, true);
        if ($schema !== null && !isset($schema->columns['id_guardia'])) {
            $this->addColumn($internacion, 'id_guardia', $this->integer()->null());
            $this->createIndex('idx_seg_nivel_internacion_id_guardia', $internacion, 'id_guardia');
        }
    }

    public function safeDown()
    {
        $internacion = '{{%seg_nivel_internacion}}';
        $schema = $this->db->schema->getTableSchema($internacion, true);
        if ($schema !== null && isset($schema->columns['id_guardia'])) {
            $this->dropIndex('idx_seg_nivel_internacion_id_guardia', $internacion);
            $this->dropColumn($internacion, 'id_guardia');
        }

        $t = '{{%efector_emergency_config}}';
        if ($this->db->schema->getTableSchema($t, true) !== null) {
            $this->dropTable($t);
        }
    }
}
