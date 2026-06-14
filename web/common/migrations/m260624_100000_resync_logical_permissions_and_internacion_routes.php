<?php

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\RbacRouteGhostInheritanceService;
use yii\db\Migration;
use yii\db\Query;

/**
 * Repara herencia de rutas clínicas (internación / epicrisis) y re-sincroniza grants rol → permiso lógico.
 *
 * Tras corregir inheritRoleGrantsFromRoute (roles transitivos vía permisos intermedios).
 */
class m260624_100000_resync_logical_permissions_and_internacion_routes extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> ruta hija => ruta padre */
    private const ROUTE_INHERIT = [
        '/api/clinical/episode-of-care/by-internacion' => '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/alta-formulario' => '/api/clinical/episode-of-care/by-internacion',
        '/api/clinical/internacion/cambio-cama-formulario' => '/api/clinical/internacion/alta-formulario',
        '/api/clinical/internacion/plantillas-epicrisis' => '/api/clinical/internacion/alta-formulario',
        '/api/clinical/internacion/preview-plantilla-epicrisis' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/listar-admin' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/ver' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/crear' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/actualizar' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/desactivar' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/activar' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/options' => '/api/clinical/internacion/plantillas-epicrisis',
        '/api/clinical/internacion-epicrisis-plantilla/*' => '/api/clinical/internacion/plantillas-epicrisis',
        '/frontend/internacion-epicrisis-plantilla/index' => '/api/clinical/internacion/plantillas-epicrisis',
        '/frontend/internacion-epicrisis-plantilla/create' => '/api/clinical/internacion/plantillas-epicrisis',
        '/frontend/internacion-epicrisis-plantilla/update' => '/api/clinical/internacion/plantillas-epicrisis',
        '/frontend/internacion-epicrisis-plantilla/toggle-activo' => '/api/clinical/internacion/plantillas-epicrisis',
        '/frontend/internacion-epicrisis-plantilla/*' => '/api/clinical/internacion/plantillas-epicrisis',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260624_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260624_100000: sin auth_item/auth_item_child, omitido.\n";

            return;
        }

        $now = time();
        foreach (array_keys(self::ROUTE_INHERIT) as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }
        $routeLinks = (new RbacRouteGhostInheritanceService())->propagateChain(self::ROUTE_INHERIT);

        $sync = (new CatalogPermissionSyncService())->sync(true);
        echo sprintf(
            "m260624_100000: enlaces_ruta=%d sync_creados=%d sync_enlaces=%d sync_grants_rol=%d\n",
            $routeLinks,
            $sync['created'],
            $sync['linked'],
            $sync['role_grants']
        );
        foreach ($sync['errors'] as $err) {
            echo '  - ' . $err . "\n";
        }
    }

    public function safeDown()
    {
        echo "m260624_100000: safeDown no revierte enlaces ni grants.\n";
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API (resync internación / epicrisis)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }
}
