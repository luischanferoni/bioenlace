<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: preferencias del asistente (extracto de HC) para el paciente.
 */
class m260818_120001_api_asistente_preferencias_paciente_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROLE_PACIENTE = 'paciente';

    private const PARENT_ROUTE = '/api/asistente/enviar';

    /** @var list<string> */
    private const ROUTES = [
        '/api/asistente/preferencias-como-paciente',
        '/api/asistente/actualizar-preferencias-como-paciente',
        '/api/chat/preferencias-como-paciente',
        '/api/chat/actualizar-preferencias-como-paciente',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260818_120001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260818_120001: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, $route);
                $this->ensureChild($childTable, self::ROLE_PACIENTE, $route);
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

        foreach (self::ROUTES as $route) {
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API asistente: preferencias de extracto de HC (paciente)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::PARENT_ROUTE])
            ->column($this->db);

        $parents[] = self::ROLE_PACIENTE;

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            $this->ensureChild($childTable, $parent, $newRoute);
        }
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ($parent === $child) {
            return;
        }
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if (!(new Query())->from($authItem)->where(['name' => $parent])->exists($this->db)) {
            return;
        }
        if ((new Query())->from($childTable)->where([
            'parent' => $parent,
            'child' => $child,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
    }
}
