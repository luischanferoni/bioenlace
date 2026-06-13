<?php

use common\components\Core\Permission\CatalogPermissionSyncService;
use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: permiso lógico InternacionEpicrisisPlantilla.admin + enlaces ruta y roles con listado_pacientes.
 */
class m260623_100000_internacion_epicrisis_plantilla_logical_permission extends Migration
{
    private const PERMISSION = 'InternacionEpicrisisPlantilla.admin';

    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const ROUTES = [
        '/api/clinical/internacion-epicrisis-plantilla/listar-admin',
        '/api/clinical/internacion-epicrisis-plantilla/ver',
        '/api/clinical/internacion-epicrisis-plantilla/crear',
        '/api/clinical/internacion-epicrisis-plantilla/actualizar',
        '/api/clinical/internacion-epicrisis-plantilla/desactivar',
        '/api/clinical/internacion-epicrisis-plantilla/activar',
        '/api/clinical/internacion-epicrisis-plantilla/options',
        '/api/clinical/internacion-epicrisis-plantilla/*',
        '/frontend/internacion-epicrisis-plantilla/index',
        '/frontend/internacion-epicrisis-plantilla/create',
        '/frontend/internacion-epicrisis-plantilla/update',
        '/frontend/internacion-epicrisis-plantilla/toggle-activo',
        '/frontend/internacion-epicrisis-plantilla/*',
    ];

    private const ROLE_INHERIT_FROM = 'listado_pacientes';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260623_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260623_100000: sin auth_item/auth_item_child, omitido.\n";

            return;
        }

        $sync = (new CatalogPermissionSyncService())->sync(true);
        echo sprintf(
            "m260623_100000 sync: creados=%d enlaces=%d grants_rol=%d\n",
            $sync['created'],
            $sync['linked'],
            $sync['role_grants']
        );

        $now = time();
        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'ABM plantillas epicrisis internación',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            $this->ensureChildLink($childTable, self::PERMISSION, $route);
        }

        $syncService = new CatalogPermissionSyncService();
        $roleGrants = 0;
        foreach ($syncService->resolveRoleNamesWithAccessToItem(self::ROLE_INHERIT_FROM) as $role) {
            if ($this->ensureChildLink($childTable, $role, self::PERMISSION)) {
                $roleGrants++;
            }
        }

        echo "m260623_100000: rutas enlazadas=" . count(self::ROUTES) . " grants_rol={$roleGrants}\n";
    }

    public function safeDown()
    {
        echo "m260623_100000: safeDown no revierte permiso lógico ni enlaces.\n";
    }

    private function ensureChildLink(string $childTable, string $parent, string $child): bool
    {
        if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists($this->db)) {
            return false;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();

        return true;
    }
}
