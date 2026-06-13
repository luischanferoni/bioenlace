<?php

namespace console\controllers;

use common\components\Core\Permission\CatalogPermissionSyncService;
use common\components\Core\Permission\DataAccessGrantMigratorService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Sincroniza permisos lógicos del catálogo declarativo → auth_item.
 */
class CatalogPermissionController extends Controller
{
    /** @var bool Copiar asignaciones rol→ruta hacia rol→permiso lógico */
    public bool $inheritRoles = true;

    /** @var bool Desactivar filas en data_access_role_grant tras migrar */
    public bool $deactivateLegacyGrants = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['inheritRoles', 'deactivateLegacyGrants']);
    }

    public function actionSync(): int
    {
        $result = (new CatalogPermissionSyncService())->syncAll($this->inheritRoles, $this->deactivateLegacyGrants);
        $catalog = $result['catalog'];
        $grants = $result['grants'];

        $this->stdout(sprintf(
            "Catálogo: creados=%d enlazados=%d grants_rol=%d omitidos=%d\n",
            $catalog['created'],
            $catalog['linked'],
            $catalog['role_grants'],
            $catalog['skipped']
        ));
        $this->stdout(sprintf(
            "Grants legacy: procesados=%d permisos_creados=%d enlaces_rol=%d omitidos=%d desactivados=%d\n",
            $grants['grants_processed'],
            $grants['permissions_created'],
            $grants['role_links_added'],
            $grants['grants_skipped'],
            $grants['deactivated']
        ));

        foreach (array_merge($catalog['errors'], $grants['errors']) as $err) {
            $this->stderr(' - ' . $err . "\n");
        }
        foreach ($grants['warnings'] as $warn) {
            $this->stdout(' [warn] ' . $warn . "\n");
        }

        return ($catalog['errors'] === [] && $grants['errors'] === []) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionMigrateGrants(): int
    {
        $grants = (new DataAccessGrantMigratorService())->migrate($this->deactivateLegacyGrants);
        $this->stdout(sprintf(
            "Migración data_access_role_grant: procesados=%d permisos_creados=%d enlaces_rol=%d omitidos=%d desactivados=%d\n",
            $grants['grants_processed'],
            $grants['permissions_created'],
            $grants['role_links_added'],
            $grants['grants_skipped'],
            $grants['deactivated']
        ));
        foreach ($grants['warnings'] as $warn) {
            $this->stdout(' [warn] ' . $warn . "\n");
        }
        foreach ($grants['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $grants['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionList(): int
    {
        foreach ((new CatalogPermissionSyncService())->collectDefinitions() as $def) {
            $route = $def['legacy_route'] !== '' ? ' → ' . $def['legacy_route'] : '';
            $this->stdout($def['key'] . ' [' . $def['kind'] . ']' . $route . "\n");
        }

        return ExitCode::OK;
    }
}
