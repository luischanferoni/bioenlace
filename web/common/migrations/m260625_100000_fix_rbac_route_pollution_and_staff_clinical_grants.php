<?php

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\RbacRouteGhostInheritanceService;
use yii\db\Migration;
use yii\db\Query;

/**
 * Limpia enlaces permiso→ruta erróneos (m260624) y propaga rutas ghost vía roles.
 * Asigna permisos lógicos clínicos a roles con acceso internación (listado_pacientes / front_listado_internacion).
 */
class m260625_100000_fix_rbac_route_pollution_and_staff_clinical_grants extends Migration
{
    private const CANONICAL_EPISODE_ROUTE = '/api/clinical/episode-of-care/by-internacion';

    /** @var list<string> */
    private const POLLUTED_PERMISSION_PARENTS = [
        'Internacion.discharge',
        'Internacion.change_bed',
        'Internacion.update',
    ];

    /** @var array<string, string> */
    private const ROUTE_CHAIN = [
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

    /** @var list<string> */
    private const STAFF_CLINICAL_PERMISSIONS = [
        'Internacion.view_map',
        'Internacion.create',
        'Internacion.discharge',
        'Internacion.change_bed',
        'GuardiaEpisode.view_board',
        'GuardiaEpisode.triage',
    ];

    /** @var list<string> */
    private const STAFF_ACCESS_SOURCES = [
        'listado_pacientes',
        'front_listado_internacion',
    ];

    /** @var list<string> */
    private const EPICRISIS_ADMIN_ROLES = [
        'AdminEfector',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260625_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260625_100000: sin auth_item_child, omitido.\n";

            return;
        }

        $removed = $this->db->createCommand()->delete($childTable, [
            'and',
            ['parent' => self::POLLUTED_PERMISSION_PARENTS],
            ['!=', 'child', self::CANONICAL_EPISODE_ROUTE],
        ])->execute();

        $routeGrants = (new RbacRouteGhostInheritanceService())->propagateChain(self::ROUTE_CHAIN);

        $sync = new CatalogPermissionSyncService();
        $logicalGrants = 0;
        foreach (self::STAFF_ACCESS_SOURCES as $source) {
            $logicalGrants += $sync->grantPermissionsToRolesWithAccessToItem(
                $source,
                self::STAFF_CLINICAL_PERMISSIONS
            );
        }

        $epicrisisGrants = 0;
        foreach (self::EPICRISIS_ADMIN_ROLES as $role) {
            if ((new Query())->from($childTable)->where([
                'parent' => $role,
                'child' => 'InternacionEpicrisisPlantilla.admin',
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $role,
                'child' => 'InternacionEpicrisisPlantilla.admin',
            ])->execute();
            $epicrisisGrants++;
        }

        echo sprintf(
            "m260625_100000: enlaces_erroneos=%d rutas_rol=%d permisos_clinicos=%d epicrisis_admin=%d\n",
            $removed,
            $routeGrants,
            $logicalGrants,
            $epicrisisGrants
        );
    }

    public function safeDown()
    {
        echo "m260625_100000: safeDown no revierte limpieza ni grants.\n";
    }
}
