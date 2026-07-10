<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC API profesional-cobertura (roster EMER/IMP) + intents.
 */
class m260710_100001_api_profesional_cobertura_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const INHERIT_FROM = '/api/profesional-agenda/listar';

    private const ROUTES = [
        '/api/profesional-cobertura/gestionar',
        '/api/profesional-cobertura/listar',
        '/api/profesional-cobertura/listar-para-recurso',
        '/api/profesional-cobertura/crear',
        '/api/profesional-cobertura/crear-para-recurso',
        '/api/profesional-cobertura/actualizar',
        '/api/profesional-cobertura/actualizar-para-recurso',
        '/api/profesional-cobertura/eliminar',
        '/api/profesional-cobertura/eliminar-para-recurso',
    ];

    private const INTENTS = [
        'profesional-cobertura.gestionar-propio',
        'profesional-cobertura.gestionar-staff',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $now = time();
        foreach (self::ROUTES as $route) {
            $this->ensureItem($authItem, $route, self::ROUTE_TYPE, 'API cobertura profesional', $now);
        }
        foreach (self::INTENTS as $intent) {
            $this->ensureItem($authItem, $intent, self::PERMISSION_TYPE, 'Intent ' . $intent, $now);
        }

        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        foreach (self::ROUTES as $route) {
            $this->inheritFrom($childTable, self::INHERIT_FROM, $route);
        }
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-propio', '/api/profesional-cobertura/gestionar');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-staff', '/api/profesional-cobertura/gestionar');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-propio', '/api/profesional-cobertura/listar');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-propio', '/api/profesional-cobertura/crear');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-propio', '/api/profesional-cobertura/actualizar');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-propio', '/api/profesional-cobertura/eliminar');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-staff', '/api/profesional-cobertura/listar-para-recurso');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-staff', '/api/profesional-cobertura/crear-para-recurso');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-staff', '/api/profesional-cobertura/actualizar-para-recurso');
        $this->linkPermissionToRoute($childTable, 'profesional-cobertura.gestionar-staff', '/api/profesional-cobertura/eliminar-para-recurso');
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $all = array_merge(self::ROUTES, self::INTENTS);
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $all])->execute();
            $this->db->createCommand()->delete($childTable, ['parent' => self::INTENTS])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $all])->execute();
    }

    private function ensureItem(string $authItem, string $name, int $type, string $description, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => $type,
            'description' => $description,
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
