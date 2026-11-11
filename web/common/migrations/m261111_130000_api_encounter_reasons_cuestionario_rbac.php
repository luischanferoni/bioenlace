<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: cuestionario pre-turno de motivos (hereda de los permisos del chat de motivos).
 */
class m261111_130000_api_encounter_reasons_cuestionario_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const API_PARENT_ROUTE = '/api/encounter-reasons/listar-mensajes';

    /** @var array<string, string> ruta => descripción */
    private const ROUTES = [
        '/api/encounter-reasons/cuestionario' => 'API encounter-reasons: cuestionario pre-turno',
        '/api/encounter-reasons/responder-cuestionario' => 'API encounter-reasons: responder cuestionario pre-turno',
    ];

    public function safeUp(): void
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

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::API_PARENT_ROUTE])
            ->column($this->db);

        $now = time();
        foreach (self::ROUTES as $route => $description) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => $description,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }

            foreach ($parents as $parent) {
                if (!is_string($parent) || $parent === '') {
                    continue;
                }
                $exists = (new Query())
                    ->from($childTable)
                    ->where(['parent' => $parent, 'child' => $route])
                    ->exists($this->db);
                if ($exists) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $route,
                ])->execute();
            }
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        foreach (array_keys(self::ROUTES) as $route) {
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->delete($childTable, ['child' => $route]);
            }
            if ($this->db->schema->getTableSchema($authItem, true) !== null) {
                $this->delete($authItem, ['name' => $route]);
            }
        }
    }
}
