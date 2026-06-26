<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: registro de paciente por personal (admin web, sin MPI).
 */
class m260627_120000_api_registro_staff_paciente_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/registro/registrar-como-staff',
        '/api/registro/preview-renaper-como-staff',
        '/api/registro/crear-sesion-didit-como-staff',
    ];

    /** Staff que ya puede buscar/alta operativa de personas */
    private const PARENT_ROUTE = '/personas/buscar-persona';

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
        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, self::PARENT_ROUTE, $route);
        }
    }

    public function safeDown()
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

        $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API registro paciente staff',
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

        if ($parents === []) {
            $parents = (new Query())
                ->select('name')
                ->from($this->db->schema->getRawTableName('{{%auth_item}}'))
                ->where(['name' => $parentRoute, 'type' => self::ROUTE_TYPE])
                ->column($this->db);
        }

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $childRoute])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $childRoute,
            ])->execute();
        }
    }
}
