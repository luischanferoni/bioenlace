<?php

use yii\db\Migration;

/**
 * RBAC capabilities guardia/encounter/panel: registra permisos assignables UI nativa,
 * grants por rol, enlaces intent→capability y propagación panel→rutas guardia.
 */
class m260814_120000_guardia_capabilities_rbac extends Migration
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
            propagateFromHomePanel: true
        );
    }

    public function safeDown(): void
    {
    }
}
