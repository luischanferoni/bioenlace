<?php

use yii\db\Migration;

/**
 * Agregado diario de uso de IA por contexto y efector.
 *
 * El detalle por llamada va a un JSONL (AIUsageRecorder); esta tabla guarda el
 * rollup que consultan el reporte CLI y el dashboard. No contiene prompts ni
 * respuestas ni identificadores de paciente.
 */
class m261112_160000_create_ai_usage_daily extends Migration
{
    private const TABLE = '{{%ai_usage_daily}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'usage_date' => $this->date()->notNull(),
            'context' => $this->string(64)->notNull(),
            'id_efector' => $this->integer()->notNull()->defaultValue(0),
            'calls' => $this->integer()->notNull()->defaultValue(0),
            'errors' => $this->integer()->notNull()->defaultValue(0),
            'prompt_tokens' => $this->bigInteger()->notNull()->defaultValue(0),
            'cached_tokens' => $this->bigInteger()->notNull()->defaultValue(0),
            'output_tokens' => $this->bigInteger()->notNull()->defaultValue(0),
            'thoughts_tokens' => $this->bigInteger()->notNull()->defaultValue(0),
            'latency_ms_sum' => $this->bigInteger()->notNull()->defaultValue(0),
            'cost_usd' => $this->decimal(14, 8)->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex('uq_ai_usage_daily_key', self::TABLE, ['usage_date', 'context', 'id_efector'], true);
        $this->createIndex('idx_ai_usage_daily_date', self::TABLE, ['usage_date']);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }
}
