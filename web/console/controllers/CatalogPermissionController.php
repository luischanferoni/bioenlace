<?php

namespace console\controllers;

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
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
        $catalog = (new CatalogPermissionSyncService())->sync($this->inheritRoles);

        $this->stdout(sprintf(
            "Catálogo: creados=%d enlazados=%d grants_rol=%d omitidos=%d\n",
            $catalog['created'],
            $catalog['linked'],
            $catalog['role_grants'],
            $catalog['skipped']
        ));

        foreach ($catalog['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $catalog['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionList(): int
    {
        foreach ((new CatalogPermissionSyncService())->collectDefinitions() as $def) {
            $route = $def['legacy_route'] !== '' ? ' → ' . $def['legacy_route'] : '';
            $this->stdout($def['key'] . ' [' . $def['kind'] . ']' . $route . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Añade `permission:` explícito a intents CRUD que aún lo infieren.
     */
    public function actionSeedPermissions(): int
    {
        $base = \common\components\Platform\Assistant\Catalog\IntentSchemaPaths::baseDir();
        $updated = 0;
        foreach (\common\components\Platform\Assistant\Catalog\IntentSchemaPaths::CATEGORIES as $cat) {
            foreach (glob($base . DIRECTORY_SEPARATOR . $cat . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $path) {
                $raw = (string) file_get_contents($path);
                $data = \Symfony\Component\Yaml\Yaml::parseFile($path);
                if (!is_array($data)) {
                    continue;
                }
                $intentId = trim((string) ($data['intent_id'] ?? basename($path, '.yaml')));
                if (trim((string) ($data['permission'] ?? '')) !== '' || preg_match('/^permission\s*:/m', $raw)) {
                    continue;
                }
                $permission = \common\components\Platform\Core\Permission\IntentPermissionResolver::resolve($intentId, $data);
                if ($permission === '' || strncmp($permission, '/api/', 5) === 0) {
                    $this->stderr("Skip {$intentId}\n");
                    continue;
                }
                $lines = preg_split('/\r\n|\n|\r/', $raw);
                $insertAt = 0;
                foreach ($lines as $i => $line) {
                    if (preg_match('/^intent_id\s*:/', $line)) {
                        $insertAt = $i + 1;
                        break;
                    }
                }
                array_splice($lines, $insertAt, 0, ['permission: ' . $permission]);
                file_put_contents($path, implode("\n", $lines));
                $this->stdout(basename($path) . ' => ' . $permission . "\n");
                $updated++;
            }
        }
        $this->stdout("Actualizados: {$updated}\n");

        return ExitCode::OK;
    }
}
