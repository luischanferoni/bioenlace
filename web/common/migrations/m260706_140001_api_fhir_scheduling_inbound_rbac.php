<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: onboarding Schedule HAPI y pull inbound.
 */
class m260706_140001_api_fhir_scheduling_inbound_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PARENT = '/api/profesional-efector-servicio/crear-flow';

    /** @var list<string> */
    private const ROUTES = [
        '/api/profesional-efector-servicio/listar-schedules-hapi',
        '/api/profesional-efector-servicio/preview-vinculo-schedule-hapi',
        '/api/profesional-efector-servicio/confirmar-vinculo-schedule-hapi',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260706_140001: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260706_140001: sin tablas RBAC, omitido.\n";

            return;
        }

        $now = time();
        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, self::PARENT, $route);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            foreach (self::ROUTES as $route) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            foreach (self::ROUTES as $route) {
                $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
            }
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API agendamiento FHIR entrante — onboarding Schedule',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->columnExists($authItem, 'group_code')) {
            $row['group_code'] = $this->resolveGroupCode($authItem, self::PARENT);
        }

        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function resolveGroupCode(string $authItem, string $parentRoute): ?string
    {
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

        return (new Query())->from($groupTable)->where(['code' => $parentGroup])->exists($this->db)
            ? $parentGroup
            : null;
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!(new Query())->from($childTable)->where(['parent' => $parent, 'child' => $newRoute])->exists($this->db)) {
                $this->db->createCommand()->insert($childTable, ['parent' => $parent, 'child' => $newRoute])->execute();
            }
        }

        if (!(new Query())->from($childTable)->where(['parent' => $parentRoute, 'child' => $newRoute])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, ['parent' => $parentRoute, 'child' => $newRoute])->execute();
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
