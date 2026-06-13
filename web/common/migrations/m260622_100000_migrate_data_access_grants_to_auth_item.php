<?php

use common\components\Core\Permission\CatalogPermissionSyncService;
use common\components\Core\Permission\DataAccessGrantMigratorService;
use yii\db\Migration;

/**
 * RBAC: sync catálogo declarativo + migración data_access_role_grant → auth_item (atributos).
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

        $grants = (new DataAccessGrantMigratorService())->migrate(false);
        echo sprintf(
            "m260622_100000 grants: procesados=%d permisos=%d enlaces_rol=%d omitidos=%d\n",
            $grants['grants_processed'],
            $grants['permissions_created'],
            $grants['role_links_added'],
            $grants['grants_skipped']
        );
        foreach ($grants['warnings'] as $w) {
            echo '  [warn] ' . $w . "\n";
        }
        foreach (array_merge($catalog['errors'], $grants['errors']) as $e) {
            echo '  [error] ' . $e . "\n";
        }
    }

    public function safeDown()
    {
        echo "m260622_100000: safeDown no revierte migración de grants.\n";
    }
}
