<?php

use yii\db\Migration;

/**
 * Copia grants legacy analisis / front_ver_historial_paciente → capabilities encounter.
 */
class m260814_140000_encounter_legacy_capability_grants extends Migration
{
    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        if (!class_exists(\common\components\Platform\Core\Permission\IntentGrantMigrationService::class)) {
            return;
        }

        (new \common\components\Platform\Core\Permission\IntentGrantMigrationService())->migrate();
    }

    public function safeDown(): void
    {
    }
}
