<?php

use yii\db\Migration;

/**
 * Métricas diarias materializadas de guardia (job nocturno).
 */
class m260603_100003_guardia_metrics_daily extends Migration
{
    public function safeUp()
    {
        $t = '{{%guardia_metrics_daily}}';
        if ($this->db->schema->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'id_efector' => $this->integer()->notNull(),
                'fecha' => $this->date()->notNull(),
                'ingresos' => $this->integer()->notNull()->defaultValue(0),
                'sin_triage' => $this->integer()->notNull()->defaultValue(0),
                'minutos_mediana_triage' => $this->integer()->null(),
                'minutos_mediana_medico' => $this->integer()->null(),
                'payload_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_guardia_metrics_efector_fecha', $t, ['id_efector', 'fecha'], true);
        }
    }

    public function safeDown()
    {
        $t = '{{%guardia_metrics_daily}}';
        if ($this->db->schema->getTableSchema($t, true) !== null) {
            $this->dropTable($t);
        }
    }
}
