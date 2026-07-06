<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC + intent asistente: bandeja staff de solicitudes de tutela (régimen A).
 */
class m260706_110000_person_representation_staff_tutela_ui_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const INTENT_ID = 'personas.verificar-tutela-staff-flow';

    private const UI_ROUTE = '/api/person-representation/solicitudes-tutela-pendientes-para-staff';

    private const VERIFY_ROUTE = '/api/person-representation/verificar-vinculo-para-staff';

    private const INTENT_PARENT = 'tratamiento.adherencia-resumen-staff';

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
        $this->ensureRoute($authItem, self::UI_ROUTE, $now);
        $this->inheritFrom($childTable, self::VERIFY_ROUTE, self::UI_ROUTE);

        $this->ensurePermission($authItem, self::INTENT_ID, $now);
        $this->inheritFrom($childTable, self::INTENT_PARENT, self::INTENT_ID);
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

        $names = [self::UI_ROUTE, self::INTENT_ID];
        $this->db->createCommand()->delete($childTable, ['child' => $names])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => $names])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API representación: solicitudes tutela pending (staff UI)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parent, string $child): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parent])
            ->column($this->db);

        foreach ($parents as $parentRole) {
            if (!is_string($parentRole) || $parentRole === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parentRole,
                'child' => $child,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parentRole,
                'child' => $child,
            ])->execute();
        }
    }
}
