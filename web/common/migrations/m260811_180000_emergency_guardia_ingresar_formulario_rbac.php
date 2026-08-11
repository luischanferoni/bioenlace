<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * UI JSON de ingreso a guardia hereda el mismo permiso que POST ingresar.
 */
class m260811_180000_emergency_guardia_ingresar_formulario_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const FORM_ROUTE = '/api/clinical/emergency-guardia/ingresar-formulario';

    private const SEARCH_ROUTE = '/api/clinical/emergency-guardia/buscar-persona-ingreso';

    private const INHERIT_FROM = '/api/clinical/emergency-guardia/ingresar';

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
        foreach ([self::FORM_ROUTE, self::SEARCH_ROUTE] as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API urgencias: ingreso a guardia (UI)',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ($parents as $parent) {
            foreach ([self::FORM_ROUTE, self::SEARCH_ROUTE] as $childRoute) {
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
            foreach ([self::FORM_ROUTE, self::SEARCH_ROUTE] as $route) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
        }
        foreach ([self::FORM_ROUTE, self::SEARCH_ROUTE] as $route) {
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }
}
