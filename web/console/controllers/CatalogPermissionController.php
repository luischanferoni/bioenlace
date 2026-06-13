<?php

namespace console\controllers;

use common\components\Core\Permission\CatalogPermissionSyncService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Sincroniza permisos lógicos del catálogo declarativo → auth_item.
 */
class CatalogPermissionController extends Controller
{
    /** @var bool Copiar asignaciones rol→ruta hacia rol→permiso lógico */
    public bool $inheritRoles = true;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['inheritRoles']);
    }

    public function actionSync(): int
    {
        $result = (new CatalogPermissionSyncService())->sync($this->inheritRoles);

        $this->stdout(sprintf(
            "Sync catálogo → auth_item: creados=%d enlazados=%d grants_rol=%d omitidos=%d\n",
            $result['created'],
            $result['linked'],
            $result['role_grants'],
            $result['skipped']
        ));

        foreach ($result['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $result['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
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
