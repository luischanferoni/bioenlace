<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC paciente: home/panel hereda permisos de care-plan y turnos paciente.
 */
class m260619_100000_api_home_panel_patient_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PANEL_ROUTE = '/api/home/panel';

    /** @var list<string> */
    private const PATIENT_PARENT_ROUTES = [
        '/api/clinical/care-plan/active',
        '/api/turnos/crear-como-paciente',
    ];

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

        foreach (self::PATIENT_PARENT_ROUTES as $parent) {
            $this->inheritFrom($childTable, $parent, self::PANEL_ROUTE);
        }
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
            'description' => 'API home panel',
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
}
