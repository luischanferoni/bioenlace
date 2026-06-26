<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: preferencias de agenda del paciente (auto-reserva A01 D2).
 */
class m260704_120002_api_turnos_preferencias_agenda_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTE = '/api/turnos/preferencias-agenda-como-paciente';

    private const PARENT_ROUTE = '/api/turnos/crear-como-paciente';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260704_120002: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260704_120002: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        $this->ensureRoute($authItem, $now);
        if ($hasChild) {
            $this->inheritFrom($childTable);
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
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTE])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => self::ROUTE])->execute();
    }

    private function ensureRoute(string $authItem, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => self::ROUTE])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => self::ROUTE,
            'type' => self::ROUTE_TYPE,
            'description' => 'API turnos: preferencias de agenda del paciente',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::PARENT_ROUTE])
            ->column($this->db);

        if ($parents === []) {
            $parents = (new Query())
                ->select('name')
                ->from($this->db->schema->getRawTableName('{{%auth_item}}'))
                ->where(['name' => self::PARENT_ROUTE, 'type' => self::ROUTE_TYPE])
                ->column($this->db);
        }

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => self::ROUTE])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::ROUTE,
            ])->execute();
        }
    }
}
