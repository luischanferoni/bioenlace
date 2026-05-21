<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: rutas API `/api/clinical/episode-of-care/*` (Fase 8 internación).
 */
class m260521_100006_api_clinical_episode_of_care_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var string[] */
    private const ROUTES = [
        '/api/clinical/episode-of-care/by-internacion',
        '/api/clinical/episode-of-care/clinical-bundle',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260521_100006: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260521_100006: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
            if ($hasChild) {
                $this->inheritFromCarePlanView($childTable, $route);
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
            'description' => 'API clinical EpisodeOfCare (Fase 8)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFromCarePlanView(string $childTable, string $newRoute): void
    {
        $legacyRoute = '/api/clinical/care-plan/view';
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
