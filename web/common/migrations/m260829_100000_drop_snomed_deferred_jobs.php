<?php

use yii\db\Migration;

/**
 * Retiro pipeline SNOMED diferido (DeferredSnomedProcessor / SnomedDeferredJob).
 *
 * La codificación persiste vía EncounterAutomaticCodingService; la tabla quedó sin invocador.
 */
class m260829_100000_drop_snomed_deferred_jobs extends Migration
{
    private const TABLE = '{{%snomed_deferred_jobs}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }

    public function safeDown()
    {
        echo "m260829_100000: no se recrea snomed_deferred_jobs.\n";
    }
}
