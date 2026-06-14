<?php

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use yii\db\Migration;

/**
 * RBAC: sync catálogo declarativo → auth_item (permisos lógicos + enlaces a rutas).
 */
class m260622_100000_migrate_data_access_grants_to_auth_item extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260622_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260622_100000: sin auth_item, omitido.\n";

            return;
        }

        $catalog = (new CatalogPermissionSyncService())->sync(true);
        echo sprintf(
            "m260622_100000 catálogo: creados=%d enlazados=%d grants_rol=%d\n",
            $catalog['created'],
            $catalog['linked'],
            $catalog['role_grants']
        );
        foreach ($catalog['errors'] as $e) {
            echo '  [error] ' . $e . "\n";
        }
    }

    public function safeDown()
    {
        echo "m260622_100000: safeDown no revierte sync de catálogo.\n";
    }
}
