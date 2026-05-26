<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: internación operativa (mapa camas, indicadores, alta UI, marcar cama).
 */
class m260604_100002_api_clinical_internacion_operativa_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> */
    private const INHERIT = [
        '/api/clinical/internacion/mapa-camas' => '/api/pacientes/listar',
        '/api/clinical/internacion/indicadores-resumen' => '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/marcar-estado' => '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/alta-formulario' => '/api/clinical/episode-of-care/by-internacion',
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
        foreach (self::INHERIT as $ghost => $parent) {
            if (!(new Query())->from($authItem)->where(['name' => $ghost])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $ghost,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API internación operativa',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }

            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => $parent])
                ->column($this->db);

            foreach ($parents as $role) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $role,
                    'child' => $ghost,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $role,
                    'child' => $ghost,
                ])->execute();
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $routes = array_keys(self::INHERIT);
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
        }
    }
}
