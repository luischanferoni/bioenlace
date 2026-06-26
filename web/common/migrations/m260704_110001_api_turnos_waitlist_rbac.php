<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: lista de espera ambulatoria (paciente).
 */
class m260704_110001_api_turnos_waitlist_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const NEW_ROUTES = [
        '/api/turnos/lista-espera-inscribir-como-paciente',
        '/api/turnos/lista-espera-cancelar-como-paciente',
        '/api/turnos/lista-espera-estado-como-paciente',
        '/api/turnos/lista-espera-aceptar-oferta-como-paciente',
    ];

    private const PARENT_ROUTE = '/api/turnos/crear-como-paciente';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260704_110001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260704_110001: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::NEW_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, self::PARENT_ROUTE, $route);
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
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            foreach (self::NEW_ROUTES as $route) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
        }
        foreach (self::NEW_ROUTES as $route) {
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API turnos: lista de espera (paciente)',
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

        if ($parents === []) {
            $parents = (new Query())
                ->select('name')
                ->from($this->db->schema->getRawTableName('{{%auth_item}}'))
                ->where(['name' => $parentRoute, 'type' => self::ROUTE_TYPE])
                ->column($this->db);
        }

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $newRoute])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }
    }
}
