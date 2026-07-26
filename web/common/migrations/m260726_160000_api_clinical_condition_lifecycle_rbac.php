<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: Condition lifecycle (resolve / inactivate / transition / open-problems).
 */
class m260726_160000_api_clinical_condition_lifecycle_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var list<string> */
    private const ROUTES = [
        '/api/clinical/condition/open-problems',
        '/api/clinical/condition/resolve',
        '/api/clinical/condition/inactivate',
        '/api/clinical/condition/transition',
    ];

    private const INHERIT_FROM = '/api/clinical/condition/index';

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260726_160000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260726_160000: sin auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        $templateParents = [];
        if ($hasChild) {
            $templateParents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => self::INHERIT_FROM])
                ->column($this->db);
            if ($templateParents === []) {
                $templateParents = (new Query())
                    ->select('parent')
                    ->from($childTable)
                    ->where(['child' => '/api/clinical/care-plan/complete'])
                    ->column($this->db);
            }
        }

        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API clinical condition lifecycle',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            if (!$hasChild) {
                continue;
            }
            foreach ($templateParents as $parent) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $route,
                ])->exists($this->db)) {
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
        // No revierte grants productivos.
    }
}
