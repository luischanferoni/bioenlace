<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: envío de quejas paciente (API + intent asistente).
 */
class m260703_100001_api_queja_paciente_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const API_ROUTE = '/api/queja-paciente/enviar-como-paciente';

    private const API_PARENT_ROUTE = '/api/turnos/crear-como-paciente';

    private const INTENT_ID = 'plataforma.enviar-queja-como-paciente-flow';

    private const INTENT_PARENT = 'turnos.crear-como-paciente';

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
        $this->ensureRoute($authItem, self::API_ROUTE, $now);
        $this->inheritFrom($childTable, self::API_PARENT_ROUTE, self::API_ROUTE);

        $this->ensurePermission($authItem, self::INTENT_ID, $now);
        $this->inheritIntentFrom($childTable, self::INTENT_PARENT, self::INTENT_ID);
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

        $names = [self::API_ROUTE, self::INTENT_ID];
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
            'description' => 'API queja paciente: enviar como paciente',
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

    private function inheritIntentFrom(string $childTable, string $parentIntent, string $childIntent): void
    {
        $this->inheritFrom($childTable, $parentIntent, $childIntent);
    }
}
