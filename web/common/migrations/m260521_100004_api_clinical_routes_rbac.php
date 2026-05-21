<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC Webvimark: rutas API `/api/clinical/*` (Fase 4).
 *
 * Crea rutas (auth_item type=3) y replica asignaciones desde `/api/consulta/*` si existen.
 */
class m260521_100004_api_clinical_routes_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string|null> nueva => legacy (null = sin herencia) */
    private const ROUTE_PAIRS = [
        '/api/clinical/encounter/analizar' => '/api/consulta/analizar',
        '/api/clinical/encounter/guardar' => '/api/consulta/guardar',
        '/api/clinical/condition/index' => null,
        '/api/clinical/care-plan/active' => null,
        '/api/clinical/care-plan/view' => null,
        '/api/clinical/care-plan/complete' => null,
        '/api/clinical/care-plan/revoke' => null,
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260521_100004: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260521_100004: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::ROUTE_PAIRS as $newRoute => $legacyRoute) {
            $this->ensureRoute($authItem, $newRoute, $now);
            if ($hasChild && $legacyRoute !== null) {
                $this->inheritChildLinks($childTable, $legacyRoute, $newRoute);
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
        $names = array_keys(self::ROUTE_PAIRS);

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $names])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $names])->execute();
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
            'description' => 'API clinical (FHIR Fase 4)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritChildLinks(string $childTable, string $legacyRoute, string $newRoute): void
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
