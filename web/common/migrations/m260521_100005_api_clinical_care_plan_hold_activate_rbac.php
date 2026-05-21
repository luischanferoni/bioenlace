<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: rutas hold/activate de care-plan (Fase 5).
 */
class m260521_100005_api_clinical_care_plan_hold_activate_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const ROUTES = [
        '/api/clinical/care-plan/hold',
        '/api/clinical/care-plan/activate',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260521_100005: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();
        $templateParents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => '/api/clinical/care-plan/complete'])
            ->column($this->db);

        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API clinical care-plan lifecycle',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            if ($hasChild) {
                foreach ($templateParents as $parent) {
                    if (!(new Query())->from($childTable)->where(['parent' => $parent, 'child' => $route])->exists($this->db)) {
                        $this->db->createCommand()->insert($childTable, [
                            'parent' => $parent,
                            'child' => $route,
                        ])->execute();
                    }
                }
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
