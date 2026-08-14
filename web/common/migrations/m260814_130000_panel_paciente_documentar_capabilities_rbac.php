<?php

use yii\db\Migration;

/**
 * Capabilities panel paciente + encounter.documentar_nota (sync incremental).
 */
class m260814_130000_panel_paciente_documentar_capabilities_rbac extends Migration
{
    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        if (!class_exists(\common\components\Platform\Core\Permission\CapabilityPermissionSyncService::class)) {
            return;
        }

        (new \common\components\Platform\Core\Permission\CapabilityPermissionSyncService())->sync(
            applyDefaultRoles: true,
            linkRelatedIntents: true,
            propagateFromHomePanel: false
        );
    }

    public function safeDown(): void
    {
    }
}
