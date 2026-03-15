<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * Migra permisos/rutas de frontend a API en auth_item y auth_item_child.
 *
 * Uso:
 *   php yii migrate-permissions-frontend-to-api   # modo dry-run (no escribe)
 *   php yii migrate-permissions-frontend-to-api --apply   # aplica cambios
 *
 * Convierte rutas tipo "frontend/turnos/index" en "api/v1/turnos/index"
 * y actualiza las asignaciones (auth_item_child) para que los roles/permisos
 * que tenían la ruta frontend pasen a tener la ruta API.
 * Opcionalmente elimina las rutas frontend antiguas.
 */
class MigratePermissionsFrontendToApiController extends Controller
{
    /** @var bool Si true, aplica cambios en BD. Si false, solo muestra qué se haría. */
    public $apply = false;

    /** @var bool Si true, después de migrar elimina los auth_item y auth_item_child de rutas frontend. */
    public $deleteFrontend = false;

    /** Prefijo de ruta frontend (ej. frontend) */
    public $frontendPrefix = 'frontend';

    /** Prefijo de ruta API (ej. api/v1) */
    public $apiPrefix = 'api/v1';

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['apply', 'deleteFrontend']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['a' => 'apply', 'd' => 'deleteFrontend']);
    }

    public function actionIndex()
    {
        $db = Yii::$app->db;
        $frontendPattern = $this->frontendPrefix . '/%';

        // Rutas (type = 2 es permission en Yii2 RBAC; type = 3 a veces es route en webvimark)
        $query = (new Query())
            ->from('auth_item')
            ->where(['like', 'name', $this->frontendPrefix . '/', false])
            ->orderBy('name');
        $items = $query->all($db);

        if (empty($items)) {
            $this->stdout("No se encontraron ítems con prefijo '{$this->frontendPrefix}/' en auth_item.\n");
            return ExitCode::OK;
        }

        $this->stdout("Se encontraron " . count($items) . " ítem(s) a migrar.\n");
        $map = []; // frontend_route => api_route
        foreach ($items as $item) {
            $name = $item['name'];
            $suffix = substr($name, strlen($this->frontendPrefix) + 1);
            $apiName = $this->apiPrefix . '/' . $suffix;
            $map[$name] = $apiName;
            $this->stdout("  {$name} => {$apiName}\n");
        }

        if (!$this->apply) {
            $this->stdout("\nModo dry-run. Ejecuta con --apply para aplicar cambios.\n");
            return ExitCode::OK;
        }

        $transaction = $db->beginTransaction();
        try {
            foreach ($map as $frontendName => $apiName) {
                // Crear auth_item para la ruta API si no existe
                $exists = (new Query())->from('auth_item')->where(['name' => $apiName])->exists($db);
                if (!$exists) {
                    $itemType = 2;
                    foreach ($items as $it) {
                        if ($it['name'] === $frontendName) {
                            $itemType = (int) $it['type'];
                            break;
                        }
                    }
                    $db->createCommand()->insert('auth_item', [
                        'name' => $apiName,
                        'type' => $itemType,
                        'description' => 'Migrado desde ' . $frontendName,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ])->execute();
                    $this->stdout("Creado auth_item: {$apiName}\n");
                }

                // auth_item_child: quienes tenían la ruta frontend como hijo, agregar la ruta API como hijo
                $parents = (new Query())->select('parent')->from('auth_item_child')->where(['child' => $frontendName])->column($db);
                foreach ($parents as $parent) {
                    $childExists = (new Query())->from('auth_item_child')->where(['parent' => $parent, 'child' => $apiName])->exists($db);
                    if (!$childExists) {
                        $db->createCommand()->insert('auth_item_child', [
                            'parent' => $parent,
                            'child' => $apiName,
                        ])->execute();
                        $this->stdout("  Asignado hijo {$apiName} a permiso/rol: {$parent}\n");
                    }
                }
            }

            if ($this->deleteFrontend) {
                foreach (array_keys($map) as $frontendName) {
                    $db->createCommand()->delete('auth_item_child', ['child' => $frontendName])->execute();
                    $db->createCommand()->delete('auth_item', ['name' => $frontendName])->execute();
                    $this->stdout("Eliminado: {$frontendName}\n");
                }
            }

            $transaction->commit();
            $this->stdout("Migración aplicada correctamente.\n");
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
