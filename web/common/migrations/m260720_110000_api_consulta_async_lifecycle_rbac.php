<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: cancelar solicitud async (paciente) y cerrar con resolución (staff).
 */
class m260720_110000_api_consulta_async_lifecycle_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const STAFF_ROUTES = [
        '/api/consulta-async/cerrar-como-staff',
    ];

    /** @var list<string> */
    private const PACIENTE_ROUTES = [
        '/api/consulta-async/cancelar-como-paciente',
    ];

    private const STAFF_PARENT = '/api/home/panel';

    private const PACIENTE_PARENT = '/api/clinical/care-plans/recordatorios-como-paciente';

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
        foreach (array_merge(self::STAFF_ROUTES, self::PACIENTE_ROUTES) as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API consulta async: ' . $route,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        $this->linkChildren($childTable, self::STAFF_PARENT, self::STAFF_ROUTES);
        $this->linkChildren($childTable, self::PACIENTE_PARENT, self::PACIENTE_ROUTES);
    }

    /**
     * @param list<string> $routes
     */
    private function linkChildren(string $childTable, string $parentRoute, array $routes): void
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
            foreach ($routes as $route) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $route,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $route,
                ])->execute();
            }
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

        foreach (array_merge(self::STAFF_ROUTES, self::PACIENTE_ROUTES) as $route) {
            $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }
}
