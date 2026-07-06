<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: intents de representación paciente (tutela y delegación) para rol paciente.
 */
class m260706_100000_person_representation_paciente_intent_rbac extends Migration
{
    private const PERMISSION_TYPE = 2;

    private const ROUTE_TYPE = 3;

    /** Hereda permisos de un intent paciente ya concedido al rol. */
    private const INTENT_PARENT = 'turnos.crear-como-paciente';

    /** @var list<string> */
    private const INTENT_IDS = [
        'personas.designar-representante-flow',
        'personas.vincular-menor-flow',
    ];

    /** @var list<string> */
    private const API_ROUTES = [
        '/api/person-representation/designar-representante',
        '/api/person-representation/revocar-representante',
        '/api/person-representation/mis-representantes',
        '/api/person-representation/preferencias-como-paciente',
        '/api/person-representation/solicitar-menor-como-tutor',
        '/api/person-representation/mis-vinculos-como-tutor',
        '/api/person-representation/pacientes-a-cargo',
    ];

    private const API_PARENT_ROUTE = '/api/turnos/crear-como-paciente';

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
        foreach (self::INTENT_IDS as $intentId) {
            $this->ensurePermission($authItem, $intentId, $now);
            $this->inheritFrom($childTable, self::INTENT_PARENT, $intentId);
        }

        foreach (self::API_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            $this->inheritFrom($childTable, self::API_PARENT_ROUTE, $route);
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

        $names = array_merge(self::INTENT_IDS, self::API_ROUTES);
        $this->db->createCommand()->delete($childTable, ['child' => $names])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => $names])->execute();
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

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API representación paciente',
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
