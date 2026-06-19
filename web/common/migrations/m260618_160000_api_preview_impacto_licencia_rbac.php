<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: preview de impacto en turnos al cargar licencia.
 */
class m260618_160000_api_preview_impacto_licencia_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> */
    private const INHERIT = [
        '/api/profesional-efector-servicio/preview-impacto-licencia' => '/api/profesional-efector-servicio/cargar-licencia-como-profesional',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260618_160000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260618_160000: sin tablas RBAC, omitido.\n";

            return;
        }

        $now = time();
        foreach (self::INHERIT as $route => $parentRoute) {
            $this->ensureRoute($authItem, $route, $parentRoute, $now);
            $this->inheritFrom($childTable, $parentRoute, $route);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $routes = array_keys(self::INHERIT);
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
        }
    }

    private function ensureRoute(string $authItem, string $name, string $parentRoute, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API preview impacto licencia en turnos',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->columnExists($authItem, 'group_code')) {
            $row['group_code'] = $this->resolveGroupCode($authItem, $parentRoute);
        }

        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function resolveGroupCode(string $authItem, string $parentRoute): ?string
    {
        if ($parentRoute === '') {
            return null;
        }

        $parentGroup = (new Query())
            ->select('group_code')
            ->from($authItem)
            ->where(['name' => $parentRoute])
            ->scalar($this->db);

        if (!is_string($parentGroup) || $parentGroup === '') {
            return null;
        }

        $groupTable = $this->db->schema->getRawTableName('{{%auth_item_group}}');
        if ($this->db->schema->getTableSchema($groupTable, true) === null) {
            return null;
        }

        if (!(new Query())->from($groupTable)->where(['code' => $parentGroup])->exists($this->db)) {
            return null;
        }

        return $parentGroup;
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
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
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
