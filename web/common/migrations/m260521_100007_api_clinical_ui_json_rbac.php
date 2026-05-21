<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: UI JSON clínica (Fase 11).
 */
class m260521_100007_api_clinical_ui_json_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var string[] */
    private const ROUTES = [
        '/api/clinical/care-plan/ver-tratamiento-paciente',
        '/api/clinical/encounter/listar-ordenes-activas',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260521_100007: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260521_100007: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, '/api/clinical/care-plan/active', $route);
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        $exists = (new Query())->from($authItem)->where(['name' => $name])->exists($this->db);
        if ($exists) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API clinical UI JSON (Fase 11)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $legacyRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $legacyRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            $exists = (new Query())
                ->from($childTable)
                ->where(['parent' => $parent, 'child' => $newRoute])
                ->exists($this->db);
            if (!$exists) {
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $newRoute,
                ])->execute();
            }
        }
    }
}
