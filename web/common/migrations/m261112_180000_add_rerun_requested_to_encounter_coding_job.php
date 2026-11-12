<?php

use yii\db\Migration;

/**
 * Marca de reencolado para jobs que cambian mientras están corriendo.
 *
 * Si el encounter se edita durante la codificación, el job actual no se
 * interrumpe: al terminar se reencola con la ventana configurada.
 */
class m261112_180000_add_rerun_requested_to_encounter_coding_job extends Migration
{
    private const TABLE = '{{%encounter_coding_job}}';

    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema === null || isset($schema->columns['rerun_requested'])) {
            return;
        }

        $this->addColumn(
            self::TABLE,
            'rerun_requested',
            $this->tinyInteger()->notNull()->defaultValue(0)->after('attempts')
        );
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema(self::TABLE, true);
        if ($schema !== null && isset($schema->columns['rerun_requested'])) {
            $this->dropColumn(self::TABLE, 'rerun_requested');
        }
    }
}
