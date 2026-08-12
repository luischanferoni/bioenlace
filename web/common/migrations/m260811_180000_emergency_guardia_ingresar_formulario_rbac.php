<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Búsqueda de pacientes para ingreso a guardia hereda el mismo permiso que POST ingresar.
 */
class m260811_180000_emergency_guardia_ingresar_formulario_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

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
        if (!(new Query())->from($authItem)->where(['name' => self::SEARCH_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::SEARCH_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API urgencias: búsqueda de paciente para ingreso',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => self::SEARCH_ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::SEARCH_ROUTE,
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
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::SEARCH_ROUTE])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => self::SEARCH_ROUTE])->execute();
    }
}
