<?php

namespace console\controllers;

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\CapabilityPermissionSyncService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Sincroniza permisos lógicos del catálogo declarativo → auth_item.
 */
class CatalogPermissionController extends Controller
{
    /** @var bool Copiar asignaciones rol→ruta hacia rol→permiso lógico */
    public bool $inheritRoles = true;

    /** @var bool Ejecutar borrado real (sin flag = solo listar candidatos) */
    public bool $execute = false;

    /** @var bool Aplicar default_roles del YAML al sincronizar capabilities */
    public bool $applyDefaultRoles = false;

    /** @var bool Propagar rutas guardia desde padres de /api/home/panel */
    public bool $propagatePanel = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'inheritRoles',
            'execute',
            'applyDefaultRoles',
            'propagatePanel',
        ]);
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
     * Sincroniza capabilities UI nativa (permission/capabilities/*.yaml) → auth_item.
     *
     * Staging/prod tras migrate: --applyDefaultRoles=1 --propagatePanel=1
     */
    public function actionSyncCapabilities(): int
    {
        $result = (new CapabilityPermissionSyncService())->sync(
            applyDefaultRoles: $this->applyDefaultRoles,
            linkRelatedIntents: true,
            propagateFromHomePanel: $this->propagatePanel
        );

        $this->stdout(sprintf(
            "Capabilities: creados=%d enlazados=%d grants_rol=%d intent_links=%d panel_prop=%d omitidos=%d\n",
            $result['created'],
            $result['linked'],
            $result['role_grants'],
            $result['intent_links'],
            $result['panel_propagated'],
            $result['skipped']
        ));

        foreach ($result['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $result['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionListCapabilities(): int
    {
        foreach ((new CapabilityPermissionSyncService())->collectDefinitions() as $def) {
            $routes = $def['routes'] !== [] ? ' → ' . implode(', ', $def['routes']) : '';
            $this->stdout($def['key'] . ' [' . $def['kind'] . ']' . $routes . "\n");
        }

        return ExitCode::OK;
    }

    public function actionMigrateGrants(): int
    {
        $result = (new \common\components\Platform\Core\Permission\IntentGrantMigrationService())->migrate();

        $this->stdout(sprintf(
            "Migración grants: permisos_creados=%d grants_rol=%d\n",
            $result['created_permissions'],
            $result['role_grants']
        ));
        foreach ($result['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $result['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Lista o elimina permisos atómicos legacy Entidad.atributo.* en auth_item.
     * Sin --execute=1 solo muestra candidatos (dry-run).
     */
    public function actionPruneAttributes(): int
    {
        $result = (new CatalogPermissionSyncService())->pruneLegacyAttributeAuthItems(!$this->execute);

        if ($result['dry_run']) {
            $this->stdout(sprintf("Dry-run: %d permiso(s) atributo candidatos a eliminar\n", count($result['candidates'])));
            foreach ($result['candidates'] as $key) {
                $this->stdout('  - ' . $key . "\n");
            }
            if ($result['candidates'] !== []) {
                $this->stdout("Ejecutar con --execute=1 tras validar migrate-grants\n");
            }
        } else {
            $this->stdout(sprintf("Eliminados: %d ítem(s) auth_item\n", $result['removed']));
        }

        foreach ($result['errors'] as $err) {
            $this->stderr(' - ' . $err . "\n");
        }

        return $result['errors'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
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
