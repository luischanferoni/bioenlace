<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: baja PES (intent + ruta API), mismos roles que crear-flow.
 */
class m260713_180000_api_profesional_efector_servicio_baja_flow_rbac extends Migration
{
    private const PERMISSION_TYPE = 2;
    private const ROUTE_TYPE = 3;

    private const INTENT_ID = 'profesional-efector-servicio.baja-flow';
    private const ROUTE = '/api/profesional-efector-servicio/baja-flow';
    private const SOURCE_INTENT = 'profesional-efector-servicio.crear-flow';
    private const SOURCE_ROUTE = '/api/profesional-efector-servicio/crear-flow';

    private const UI_ROUTES = [
        '/api/profesional-efector-servicio/listar-por-efector',
        '/api/profesional-efector-servicio/listar-servicios-en-efector',
        '/api/profesional-efector-servicio/preview-impacto-baja',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260713_180000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260713_180000: sin tablas auth, omitido.\n";

            return;
        }

        $now = time();
        $this->ensurePermission($authItem, self::INTENT_ID, $now);
        $this->ensureRoute($authItem, self::ROUTE, self::SOURCE_ROUTE, $now);
        $this->ensureRoute(
            $authItem,
            '/api/profesional-efector-servicio/preview-impacto-baja',
            self::ROUTE,
            $now
        );
        $this->inheritFrom($childTable, self::SOURCE_ROUTE, self::ROUTE);
        $this->inheritFrom(
            $childTable,
            self::ROUTE,
            '/api/profesional-efector-servicio/preview-impacto-baja'
        );
        $this->migrateRoleGrants($childTable, self::SOURCE_INTENT, self::INTENT_ID);
        $this->linkPermissionToRoute($childTable, self::INTENT_ID, self::ROUTE);
        foreach (self::UI_ROUTES as $uiRoute) {
            $this->linkPermissionToRoute($childTable, self::INTENT_ID, $uiRoute);
        }
        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, [
                'parent' => self::INTENT_ID,
            ])->execute();
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTE])->execute();
            $this->db->createCommand()->delete($childTable, ['child' => self::INTENT_ID])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROUTE])->execute();
            $this->db->createCommand()->delete($authItem, ['name' => self::INTENT_ID])->execute();
        }
        $this->flushIntentCatalogCache();
        $this->bumpRbacRevision();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensureRoute(string $authItem, string $name, string $parentRoute, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API baja PES (asistente)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($this->columnExists($authItem, 'group_code')) {
            $parentGroup = (new Query())
                ->select('group_code')
                ->from($authItem)
                ->where(['name' => $parentRoute])
                ->scalar($this->db);
            if (is_string($parentGroup) && $parentGroup !== '') {
                $row['group_code'] = $parentGroup;
            }
        }
        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $newRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }

        if (!(new Query())->from($childTable)->where([
            'parent' => $parentRoute,
            'child' => $newRoute,
        ])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parentRoute,
                'child' => $newRoute,
            ])->execute();
        }
    }

    private function migrateRoleGrants(string $childTable, string $source, string $intentId): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $source])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $intentId,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $intentId,
            ])->execute();
        }
    }

    private function linkPermissionToRoute(string $childTable, string $permission, string $route): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $permission,
            'child' => $route,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $permission,
            'child' => $route,
        ])->execute();
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function flushIntentCatalogCache(): void
    {
        try {
            if (!class_exists(\Yii::class, false) || !\Yii::$app || !\Yii::$app->has('cache')) {
                return;
            }
            $cache = \Yii::$app->cache;
            if ($cache === null) {
                return;
            }
            $cache->delete('yaml_intents_catalog_v6');
            $cache->delete('yaml_intents_catalog_v7');
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache.
        }
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class, false)
                || class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache de revisión.
        }
    }
}
