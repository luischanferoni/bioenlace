<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: UI JSON laboratorio paciente (asistente).
 */
class m260524_100001_api_laboratory_paciente_ui_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const NEW_ROUTES = [
        '/api/clinical/laboratory-results/mis-resultados-como-paciente' => '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-results/sincronizar-como-paciente' => '/api/clinical/laboratory-results/sincronizar',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::NEW_ROUTES as $newRoute => $parentRoute) {
            $this->ensureRoute($authItem, $newRoute, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, $parentRoute, $newRoute);
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $routes = array_keys(self::NEW_ROUTES);
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API laboratorio: UI paciente',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $newRoute])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }
    }
}
