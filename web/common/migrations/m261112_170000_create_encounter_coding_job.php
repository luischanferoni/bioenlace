<?php

use yii\db\Migration;

/**
 * Cola de codificación clínica por encounter.
 *
 * El encounter se persiste sin códigos y este job corre después, fuera del
 * request. No guarda texto clínico completo: solo los términos de diagnóstico
 * necesarios para la llamada de codificación, que se anulan al terminar.
 */
class m261112_170000_create_encounter_coding_job extends Migration
{
    private const TABLE = '{{%encounter_coding_job}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'encounter_id' => $this->integer()->notNull(),
            'diagnosis_terms_json' => $this->text()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('PENDING'),
            'attempts' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(0),
            'last_error' => $this->text()->null(),
            'scheduled_at' => $this->dateTime()->notNull(),
            'started_at' => $this->dateTime()->null(),
            'finished_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex('uq_encounter_coding_job_encounter', self::TABLE, ['encounter_id'], true);
        $this->createIndex('idx_encounter_coding_job_status', self::TABLE, ['status', 'scheduled_at']);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }
}
