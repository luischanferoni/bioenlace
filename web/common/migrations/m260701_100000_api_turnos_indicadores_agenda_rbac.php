<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Indicadores de agenda: ruta API bajo home/panel e intent turnos.indicadores-agenda-flow.
 *
 * Repone herencia si el rol recibió /api/home/panel después de m260618 y enlaza la ruta al permiso intent.
 */
class m260701_100000_api_turnos_indicadores_agenda_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const PANEL_ROUTE = '/api/home/panel';

    private const API_ROUTE = '/api/turnos/indicadores-agenda';

    private const INTENT_PERMISSION = 'turnos.indicadores-agenda-flow';

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
        $this->ensureRoute($authItem, self::PANEL_ROUTE, $now);
        $this->ensureRoute($authItem, self::API_ROUTE, $now);
        $this->ensurePermission($authItem, self::INTENT_PERMISSION, $now);

        $this->inheritFrom($childTable, self::PANEL_ROUTE, self::API_ROUTE);
        $this->linkPermissionToRoute($childTable, self::INTENT_PERMISSION, self::API_ROUTE);
    }

    public function safeDown()
    {
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API turnos indicadores agenda',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $permission, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $permission])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $permission,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $permission,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $childRoute): void
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
                'child' => $childRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $childRoute,
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
}
