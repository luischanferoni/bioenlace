<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Asegura RBAC de preview-impacto-baja heredando de crear-flow (ruta + roles).
 * Idempotente si m260713_180000 ya corrió a medias.
 */
class m260713_181000_api_pes_preview_impacto_baja_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PREVIEW_ROUTE = '/api/profesional-efector-servicio/preview-impacto-baja';
    private const BAJA_ROUTE = '/api/profesional-efector-servicio/baja-flow';
    private const SOURCE_ROUTE = '/api/profesional-efector-servicio/crear-flow';
    private const INTENT_ID = 'profesional-efector-servicio.baja-flow';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        $this->ensureRoute($authItem, self::PREVIEW_ROUTE, self::SOURCE_ROUTE, $now);
        // Mismos roles/padres que el alta PES (como preview-impacto-licencia).
        $this->inheritFrom($childTable, self::SOURCE_ROUTE, self::PREVIEW_ROUTE);
        if ((new Query())->from($authItem)->where(['name' => self::BAJA_ROUTE])->exists($this->db)) {
            $this->inheritFrom($childTable, self::BAJA_ROUTE, self::PREVIEW_ROUTE);
        }
        if ((new Query())->from($authItem)->where(['name' => self::INTENT_ID])->exists($this->db)) {
            $this->linkPermissionToRoute($childTable, self::INTENT_ID, self::PREVIEW_ROUTE);
        }

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
            $this->db->createCommand()->delete($childTable, ['child' => self::PREVIEW_ROUTE])->execute();
            $this->db->createCommand()->delete($childTable, [
                'parent' => self::INTENT_ID,
                'child' => self::PREVIEW_ROUTE,
            ])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::PREVIEW_ROUTE])->execute();
        }
        $this->bumpRbacRevision();
    }

    private function ensureRoute(string $authItem, string $name, string $parentRoute, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API preview impacto baja PES',
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

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
