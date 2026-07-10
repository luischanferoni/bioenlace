<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: elegir-pes para cobertura.
 */
class m260710_130000_api_profesional_cobertura_elegir_pes_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTE = '/api/profesional-cobertura/elegir-pes';

    private const INHERIT_FROM = '/api/profesional-cobertura/gestionar';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $now = time();
        if (!(new Query())->from($authItem)->where(['name' => self::ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API elegir PES para cobertura',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);
        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => self::ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::ROUTE,
            ])->execute();
        }

        foreach (['profesional-cobertura.gestionar-propio', 'profesional-cobertura.gestionar-staff'] as $perm) {
            if (!(new Query())->from($authItem)->where(['name' => $perm])->exists($this->db)) {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $perm,
                'child' => self::ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $perm,
                'child' => self::ROUTE,
            ])->execute();
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
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTE])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROUTE])->execute();
        }
    }
}
