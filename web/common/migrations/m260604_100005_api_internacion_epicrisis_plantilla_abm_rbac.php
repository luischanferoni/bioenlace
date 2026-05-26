<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: ABM plantillas de epicrisis (internación).
 */
class m260604_100005_api_internacion_epicrisis_plantilla_abm_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/clinical/internacion-epicrisis-plantilla/listar-admin',
        '/api/clinical/internacion-epicrisis-plantilla/ver',
        '/api/clinical/internacion-epicrisis-plantilla/crear',
        '/api/clinical/internacion-epicrisis-plantilla/actualizar',
        '/api/clinical/internacion-epicrisis-plantilla/desactivar',
        '/api/clinical/internacion-epicrisis-plantilla/activar',
    ];

    private const INHERIT_FROM = '/api/clinical/internacion/plantillas-epicrisis';

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
        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'ABM plantillas epicrisis internación',
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
            foreach (self::ROUTES as $ghost) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $ghost,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
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

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
        }
    }
}
