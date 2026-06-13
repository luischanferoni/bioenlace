<?php

use common\components\Core\Permission\CatalogPermissionSyncService;
use yii\db\Migration;

/**
 * RBAC greenfield: registra permisos lógicos del catálogo (intents + atributos) en auth_item
 * y enlaza intent → ruta legacy. Hereda grants rol→ruta hacia rol→permiso lógico.
 */
class m260621_100000_catalog_logical_permissions_rbac extends Migration
{
    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260621_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260621_100000: sin auth_item, omitido.\n";

            return;
        }

        $result = (new CatalogPermissionSyncService())->sync(true);
        echo sprintf(
            "m260621_100000: permisos creados=%d enlaces=%d grants_rol=%d omitidos=%d errores=%d\n",
            $result['created'],
            $result['linked'],
            $result['role_grants'],
            $result['skipped'],
            count($result['errors'])
        );
        foreach ($result['errors'] as $err) {
            echo '  - ' . $err . "\n";
        }
    }

    public function safeDown()
    {
        echo "m260621_100000: safeDown no revierte permisos lógicos (eliminar manualmente si aplica).\n";
    }
}
