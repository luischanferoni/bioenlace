<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: asegura `/api/clinical/encounter/guardar` para roles que ya capturan (analizar / legacy consulta).
 *
 * Cubre desajustes post-migración FHIR donde el profesional puede analizar pero no guardar.
 */
class m260709_100000_api_encounter_guardar_rbac_from_analizar extends Migration
{
    private const ROUTE_TYPE = 3;

    private const TARGET_ROUTE = '/api/clinical/encounter/guardar';

    /** @var list<string> */
    private const INHERIT_FROM = [
        '/api/consulta/guardar',
        '/api/consulta/analizar',
        '/api/clinical/encounter/analizar',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260709_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260709_100000: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        $this->ensureRoute($authItem, self::TARGET_ROUTE, $now);

        if (!$hasChild) {
            return;
        }

        foreach (self::INHERIT_FROM as $sourceRoute) {
            if (!(new Query())->from($authItem)->where(['name' => $sourceRoute])->exists($this->db)) {
                continue;
            }
            $this->inheritFrom($childTable, $sourceRoute, self::TARGET_ROUTE);
        }
    }

    public function safeDown()
    {
        // No revierte asignaciones: podrían existir por m260521 u otras migraciones.
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }

        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API clinical encounter guardar (sync analizar)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $sourceRoute, string $targetRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $sourceRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $targetRoute,
            ])->exists($this->db)) {
                continue;
            }

            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $targetRoute,
            ])->execute();
        }
    }
}
