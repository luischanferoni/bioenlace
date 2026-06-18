<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: solicitud de consulta async paciente (API desde atencion.necesito-atencion).
 */
class m260618_120000_api_consulta_async_solicitar_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const API_ROUTE = '/api/consulta-async/solicitar-como-paciente';

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
        if (!(new Query())->from($authItem)->where(['name' => self::API_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::API_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API consulta async: solicitar como paciente',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::API_PARENT_ROUTE])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => self::API_ROUTE,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::API_ROUTE,
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
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, ['child' => self::API_ROUTE])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => self::API_ROUTE])->execute();
    }
}
