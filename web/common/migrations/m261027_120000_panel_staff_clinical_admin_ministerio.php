<?php

use yii\db\Migration;

/**
 * Panel de inicio: AdminMinisterio entra a la audiencia staff y recibe `panel.staff_clinical`.
 *
 * Origen: `audience.staff_roles` del home-panel-manifest.yaml y `default_roles` de
 * permission/metadata/capabilities/panel.yaml. El sync otorga la capability a los roles
 * declarados; no modifica grants existentes.
 */
class m261027_120000_panel_staff_clinical_admin_ministerio extends Migration
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
